<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

/** Pure date handling. JetEngine timestamps encode local wall-clock time as UTC. */
final class Dates {
    public static function parse($value, string $storage, \DateTimeZone $tz): ?\DateTimeImmutable {
        if (!is_scalar($value) || is_bool($value) || '' === trim((string) $value)) return null;
        $value = trim((string) $value);
        try {
            if (in_array($storage, ['jetengine', 'unix'], true)) {
                if (!preg_match('/^-?\d{1,11}$/D', $value)) return null;
                $utc = new \DateTimeImmutable('@' . $value);
                if ('unix' === $storage) return $utc->setTimezone($tz);
                return \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc->format('Y-m-d H:i:s'), $tz) ?: null;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value)) return null;
            $date = new \DateTimeImmutable($value, $tz);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) return null;
            return $date->setTimezone($tz);
        } catch (\Throwable $error) { return null; }
    }

    /** Keep null placeholders: dropping invalid values would mispair recurring end rows. */
    public static function rows(array $rows): array {
        $out = [];
        $walk = function ($value, int $depth = 0) use (&$walk, &$out) {
            if (count($out) >= 10000 || $depth > 5) return;
            if (is_string($value) && isset($value[0]) && '[' === $value[0]) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) $value = $decoded;
            }
            if (is_array($value)) { foreach ($value as $item) $walk($item, $depth + 1); }
            else $out[] = is_scalar($value) ? $value : null;
        };
        foreach ($rows as $row) $walk($row);
        return $out;
    }

    public static function window(string $month, int $week_start, \DateTimeZone $tz): array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) throw new \InvalidArgumentException('Invalid month.');
        $first = new \DateTimeImmutable($month . '-01 00:00:00', $tz);
        $offset = ((int) $first->format('w') - $week_start + 7) % 7;
        $start = $first->modify('-' . $offset . ' days');
        return [$start, $start->modify('+42 days'), $first];
    }

    public static function boundary(\DateTimeImmutable $date, string $storage): string {
        if ('text' === $storage) return $date->format('Y-m-d');
        return (string) ('unix' === $storage ? $date->getTimestamp() : (new \DateTimeImmutable($date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC')))->getTimestamp());
    }

    /** Produce occurrences from JetEngine's saved rows; never regenerate excluded dates. */
    public static function occurrences(array $starts, array $ends, array $feed, \DateTimeImmutable $from, \DateTimeImmutable $until, array $excluded = []): array {
        $starts = self::rows($starts);
        $ends = self::rows($ends);
        $result = [];
        $seen = [];
        foreach ($starts as $index => $raw) {
            $start = self::parse($raw, $feed['storage'], $from->getTimezone());
            if (!$start || isset($seen[$start->format('c')]) || in_array($start->format('Y-m-d'), $excluded, true)) continue;
            $seen[$start->format('c')] = true;
            $end = isset($ends[$index]) ? self::parse($ends[$index], $feed['storage'], $from->getTimezone()) : null;
            if (!$end || $end < $start) $end = $start;
            $actual_end = $end;
            if (!$feed['span']) $end = $start;
            elseif ($feed['overnight'] && $end->format('Y-m-d') === $start->modify('+1 day')->format('Y-m-d') && (int) $end->format('H') < 12) $end = $start->setTime(23, 59, 59);
            if ($start >= $until || $end < $from) continue;
            // Midnight is an exclusive end: an event ending at 00:00 does not occupy the next day.
            $last = empty($feed['inclusive_end']) && $end > $start && '00:00:00' === $end->format('H:i:s') ? $end->modify('-1 second') : $end;
            $day = $start->setTime(0, 0) < $from ? $from : $start->setTime(0, 0);
            $days = [];
            while ($day < $until && $day->format('Y-m-d') <= $last->format('Y-m-d')) {
                $days[] = $day->format('Y-m-d');
                $day = $day->modify('+1 day');
            }
            if ($days) $result[] = ['start' => $start, 'end' => $actual_end, 'display_end' => $end, 'days' => $days];
        }
        return $result;
    }
}
