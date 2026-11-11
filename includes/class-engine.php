<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

final class Engine {
    public const MAX_FEEDS = 4;
    public static ?array $occurrence = null;
    private static int $render_depth = 0;

    public static function key($value): string {
        return is_scalar($value) && preg_match('/^[a-zA-Z0-9_\-]{1,191}$/D', (string) $value) ? (string) $value : '';
    }

    public static function post_ids($value): array {
        $parts = array_map('trim', explode(',', is_scalar($value) ? (string) $value : ''));
        return array_slice(array_values(array_unique(array_map('intval', array_filter($parts, static fn($id) => ctype_digit($id) && (int) $id > 0)))), 0, 200);
    }

    public static function normalize(array $settings): array {
        $config = [
            'feeds' => [], 'week_start' => max(0, min(6, (int) ($settings['week_start'] ?? get_option('start_of_week', 1)))),
            'min_year' => max(1970, min(2099, (int) ($settings['min_year'] ?? 2020))),
            'max_year' => max(1970, min(2099, (int) ($settings['max_year'] ?? 2040))),
            'nearby' => 'yes' === ($settings['nearby'] ?? 'yes'), 'hide_past' => 'yes' === ($settings['hide_past'] ?? ''),
            'mobile' => 'grid' === ($settings['mobile_view'] ?? '') ? 'grid' : 'agenda',
            'cache' => max(0, min(3600, (int) ($settings['cache_seconds'] ?? 60))),
            'title' => sanitize_text_field($settings['calendar_label'] ?? __('Calendar', 'calico-elementor-calendar')),
        ];
        if ($config['max_year'] < $config['min_year']) $config['max_year'] = $config['min_year'];
        for ($i = 1; $i <= self::MAX_FEEDS; $i++) {
            $p = 'feed_' . $i . '_';
            if ('yes' !== ($settings[$p . 'enabled'] ?? '')) continue;
            $type = sanitize_key($settings[$p . 'post_type'] ?? '');
            $key = self::key($settings[$p . 'start_key'] ?? '');
            $listing = absint($settings[$p . 'listing'] ?? 0);
            if (!$type || !$key || !$listing || !post_type_exists($type)) continue;
            $template = get_post($listing);
            if (!$template || 'jet-engine' !== $template->post_type || 'publish' !== $template->post_status || $template->post_password) continue;
            $storage = $settings[$p . 'storage'] ?? 'jetengine';
            $storage = in_array($storage, ['jetengine', 'unix', 'text'], true) ? $storage : 'jetengine';
            $end = self::key($settings[$p . 'end_key'] ?? '');
            if ('jetengine' === $storage && (!$end || $end === $key)) $end = $key . '__end_date';
            $taxonomy = sanitize_key($settings[$p . 'taxonomy'] ?? '');
            if (!$taxonomy || !is_object_in_taxonomy($type, $taxonomy)) $taxonomy = '';
            $config['feeds'][] = [
                'id' => $i, 'label' => sanitize_text_field($settings[$p . 'label'] ?? ('Feed ' . $i)),
                'post_type' => $type, 'listing' => $listing, 'start_key' => $key, 'end_key' => $end, 'storage' => $storage,
                'span' => 'yes' === ($settings[$p . 'span'] ?? 'yes'), 'overnight' => 'yes' === ($settings[$p . 'overnight'] ?? ''),
                'inclusive_end' => 'yes' === ($settings[$p . 'inclusive_end'] ?? ''),
                'order' => (int) ($settings[$p . 'order'] ?? $i), 'show_label' => 'yes' === ($settings[$p . 'show_label'] ?? ''),
                'limit' => max(1, min(500, (int) ($settings[$p . 'limit'] ?? 200))),
                'taxonomy' => $taxonomy, 'terms' => array_slice(array_values(array_filter(array_map('sanitize_title', explode(',', (string) ($settings[$p . 'terms'] ?? ''))))), 0, 50),
                'meta_key' => self::key($settings[$p . 'meta_key'] ?? ''), 'meta_value' => sanitize_text_field($settings[$p . 'meta_value'] ?? ''),
                'include_ids' => self::post_ids($settings[$p . 'include_ids'] ?? ''), 'exclude_ids' => self::post_ids($settings[$p . 'exclude_ids'] ?? ''),
            ];
        }
        usort($config['feeds'], static fn($a, $b) => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);
        return $config;
    }

    /** Only one date-row join. Correlated EXISTS avoids start/end recurrence Cartesian products. */
    public static function ids(array $feed, \DateTimeImmutable $from, \DateTimeImmutable $until, int $ttl): array {
        global $wpdb;
        $version = get_option('calico_calendar_cache_version', '1');
        $cache_key = 'ccc_' . md5(wp_json_encode([$feed, $from->format('c'), $until->format('c'), $version]));
        if ($ttl && false !== ($cached = get_transient($cache_key))) return $cached;
        $storage = $feed['storage'];
        $expr = 'text' === $storage ? 'LEFT(sm.meta_value, 10)' : 'CAST(sm.meta_value AS SIGNED)';
        $end_expr = 'text' === $storage ? 'LEFT(em.meta_value, 10)' : 'CAST(em.meta_value AS SIGNED)';
        $low = Dates::boundary($from, $storage);
        $high = Dates::boundary($until, $storage);
        // Text values may contain UTC offsets; include a one-day margin, then compare exact instants in PHP.
        if ('text' === $storage) { $low = $from->modify('-1 day')->format('Y-m-d'); $high = $until->modify('+1 day')->format('Y-m-d'); }
        $where = $wpdb->prepare("p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND sm.meta_key = %s", $feed['post_type'], $feed['start_key']);
        $overlap = '';
        if ($feed['span'] && $feed['end_key']) {
            $overlap = $wpdb->prepare(" OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} em WHERE em.post_id = p.ID AND em.meta_key = %s AND $end_expr >= %s)", $feed['end_key'], $low);
        }
        $where .= $wpdb->prepare(" AND (($expr < %s AND ($expr >= %s $overlap)) OR sm.meta_value LIKE %s OR sm.meta_value LIKE %s)", $high, $low, '[%', 'a:%');
        if ($feed['meta_key']) $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} fm WHERE fm.post_id=p.ID AND fm.meta_key=%s AND fm.meta_value=%s)", $feed['meta_key'], $feed['meta_value']);
        foreach (['include_ids' => 'IN', 'exclude_ids' => 'NOT IN'] as $key => $operator) {
            if (!empty($feed[$key])) $where .= " AND p.ID $operator (" . implode(',', array_map('intval', $feed[$key])) . ')';
        }
        if ($feed['taxonomy'] && $feed['terms']) {
            $term_slots = implode(',', array_fill(0, count($feed['terms']), '%s'));
            $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE tr.object_id=p.ID AND tt.taxonomy=%s AND t.slug IN ($term_slots))", array_merge([$feed['taxonomy']], $feed['terms']));
        }
        $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} sm ON sm.post_id=p.ID WHERE $where ORDER BY p.ID ASC LIMIT " . (int) ($feed['limit'] + 1);
        $ids = array_map('intval', $wpdb->get_col($sql));
        if ($ttl) set_transient($cache_key, $ids, $ttl);
        return $ids;
    }

    private static function listing(int $listing_id, \WP_Post $item, array $occurrence): string {
        $engine = jet_engine();
        $data = $engine->listings->data;
        $frontend = $engine->frontend;
        if (!is_callable([$frontend, 'set_listing']) || !is_callable([$frontend, 'get_listing_item']) || !is_callable([$frontend, 'get_listing_id']) || !is_callable([$data, 'set_listing_by_id'])) throw new \RuntimeException('Unsupported JetEngine listing renderer.');
        global $post;
        $old_post = $post ?? null;
        $old_object = $data->get_current_object();
        $old_listing = is_callable([$data, 'get_listing']) ? $data->get_listing() : null;
        $old_frontend_listing = $frontend->get_listing_id();
        global $wp_query;
        $old_queried = isset($wp_query) ? $wp_query->queried_object : null;
        $old_queried_id = isset($wp_query) ? $wp_query->queried_object_id : null;
        $old_occurrence = self::$occurrence;
        $level = ob_get_level();
        ob_start();
        try {
            $post = $item;
            setup_postdata($post);
            if (isset($wp_query)) { $wp_query->queried_object = $item; $wp_query->queried_object_id = $item->ID; }
            $data->set_listing_by_id($listing_id);
            $frontend->set_listing($listing_id);
            // WordPress AJAX does not fire wp_enqueue_scripts, where JetEngine normally registers dependencies.
            if (is_callable([$frontend, 'register_listing_deps']) && !wp_script_is('jet-plugins', 'registered')) $frontend->register_listing_deps();
            if (is_callable([$frontend, 'frontend_scripts'])) $frontend->frontend_scripts();
            $data->set_current_object($item);
            self::$occurrence = $occurrence;
            $html = $frontend->get_listing_item($item);
            return ob_get_clean() . (is_string($html) ? $html : '');
        } finally {
            while (ob_get_level() > $level) ob_end_clean();
            self::$occurrence = $old_occurrence;
            $frontend->set_listing($old_frontend_listing);
            if (is_callable([$data, 'get_listing']) && is_callable([$data, 'set_listing'])) $data->set_listing($old_listing);
            $data->set_current_object($old_object);
            if (isset($wp_query)) { $wp_query->queried_object = $old_queried; $wp_query->queried_object_id = $old_queried_id; }
            $post = $old_post;
            if ($old_post instanceof \WP_Post) setup_postdata($old_post); else wp_reset_postdata();
        }
    }

    public static function inner(array $config, string $month): string {
        if (self::$render_depth) return '<p>' . esc_html__('A calendar cannot be nested in its own listing template.', 'calico-elementor-calendar') . '</p>';
        self::$render_depth++;
        try { return self::build($config, $month); }
        finally { self::$render_depth--; }
    }

    private static function build(array $config, string $month): string {
        $tz = wp_timezone();
        [$from, $until, $first] = Dates::window($month, $config['week_start'], $tz);
        if ((int) $first->format('Y') < $config['min_year'] || (int) $first->format('Y') > $config['max_year']) throw new \InvalidArgumentException('Month outside the configured range.');
        $buckets = []; $notices = []; $count = 0;
        $now = new \DateTimeImmutable('now', $tz);
        foreach ($config['feeds'] as $feed) {
            $ids = self::ids($feed, $from, $until, $config['cache']);
            if (count($ids) > $feed['limit']) $notices[] = sprintf(__('The %s feed exceeds its configured item limit for this month.', 'calico-elementor-calendar'), $feed['label']);
            $ids = array_slice($ids, 0, $feed['limit']);
            if (!$ids) continue;
            // Revalidate status after a cache hit; cached IDs must never expose newly private posts.
            $posts = get_posts(['post_type' => $feed['post_type'], 'post__in' => $ids, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => count($ids), 'orderby' => 'post__in', 'suppress_filters' => false]);
            update_meta_cache('post', wp_list_pluck($posts, 'ID'));
            foreach ($posts as $item) {
                $map = get_post_meta($item->ID, '_calico_jetengine_exclusions', true);
                $excluded = is_array($map) && isset($map[$feed['start_key']]) && is_array($map[$feed['start_key']]) ? $map[$feed['start_key']] : [];
                $occurrences = Dates::occurrences(get_post_meta($item->ID, $feed['start_key'], false), $feed['end_key'] ? get_post_meta($item->ID, $feed['end_key'], false) : [], $feed, $from, $until, $excluded);
                foreach ($occurrences as $occurrence) {
                    if ($config['hide_past'] && $occurrence['end']->format('Y-m-d') < $now->format('Y-m-d')) continue;
                    if (++$count > 2000) { $notices[] = __('This month exceeds the calendar occurrence limit. Narrow the feed filters.', 'calico-elementor-calendar'); break 3; }
                    try { $html = self::listing($feed['listing'], $item, $occurrence); }
                    catch (\Throwable $error) { $notices[] = sprintf(__('The %s listing could not be rendered. Check its template and JetEngine version.', 'calico-elementor-calendar'), $feed['label']); continue; }
                    foreach ($occurrence['days'] as $day) {
                        $buckets[$day][$feed['id']]['feed'] = $feed;
                        $buckets[$day][$feed['id']]['items'][] = ['html' => $html, 'start' => $occurrence['start']->getTimestamp(), 'post' => $item->ID];
                    }
                }
            }
        }
        ob_start();
        echo '<div class="ccc-nav"><button type="button" data-ccc-step="-1" aria-label="' . esc_attr__('Previous month', 'calico-elementor-calendar') . '"' . ((int) $first->format('Y') === $config['min_year'] && '01' === $first->format('m') ? ' disabled' : '') . '>&#8249;</button>';
        echo '<div class="ccc-selects"><label><span class="ccc-sr-only">' . esc_html__('Month', 'calico-elementor-calendar') . '</span><select data-ccc-month>';
        for ($m=1; $m<=12; $m++) echo '<option value="' . $m . '"' . selected($m, (int) $first->format('n'), false) . '>' . esc_html(wp_date('F', (new \DateTimeImmutable('2000-' . $m . '-01', $tz))->getTimestamp(), $tz)) . '</option>';
        echo '</select></label><label><span class="ccc-sr-only">' . esc_html__('Year', 'calico-elementor-calendar') . '</span><select data-ccc-year>';
        for ($y=$config['min_year']; $y<=$config['max_year']; $y++) echo '<option value="' . $y . '"' . selected($y, (int) $first->format('Y'), false) . '>' . $y . '</option>';
        echo '</select></label></div><button type="button" data-ccc-step="1" aria-label="' . esc_attr__('Next month', 'calico-elementor-calendar') . '"' . ((int) $first->format('Y') === $config['max_year'] && '12' === $first->format('m') ? ' disabled' : '') . '>&#8250;</button></div>';
        echo '<p class="ccc-status ccc-sr-only" role="status" aria-live="polite">' . esc_html(wp_date('F Y', $first->getTimestamp(), $tz)) . '</p>';
        foreach (array_unique($notices) as $notice) echo '<p class="ccc-notice" role="status">' . esc_html($notice) . '</p>';
        echo '<div class="ccc-grid"><table class="ccc-table"><caption class="ccc-sr-only">' . esc_html($config['title'] . ' — ' . wp_date('F Y', $first->getTimestamp(), $tz)) . '</caption><thead><tr>';
        for ($d=0; $d<7; $d++) { $day = $from->modify('+' . $d . ' days'); echo '<th scope="col"><abbr title="' . esc_attr(wp_date('l', $day->getTimestamp(), $tz)) . '">' . esc_html(wp_date('D', $day->getTimestamp(), $tz)) . '</abbr></th>'; }
        echo '</tr></thead><tbody>';
        for ($i=0; $i<42; $i++) {
            if (0 === $i % 7) echo '<tr>';
            $day = $from->modify('+' . $i . ' days'); $date = $day->format('Y-m-d');
            $other = $day->format('Y-m') !== $month;
            $groups = (!$other || $config['nearby']) ? ($buckets[$date] ?? []) : [];
            $classes = 'ccc-day' . ($other ? ' ccc-other-month' : '') . (!$groups ? ' ccc-empty' : '') . ($date === $now->format('Y-m-d') ? ' ccc-today' : '');
            echo '<td class="' . esc_attr($classes) . '" data-date="' . esc_attr($date) . '"><div class="ccc-day-header"><time datetime="' . esc_attr($date) . '"' . ($date === $now->format('Y-m-d') ? ' aria-current="date"' : '') . '><span class="ccc-date-desktop">' . $day->format('j') . '</span><span class="ccc-date-mobile">' . esc_html(wp_date('l, F j', $day->getTimestamp(), $tz)) . '</span></time></div><div class="ccc-day-content">';
            foreach ($groups as $group) {
                $feed = $group['feed'];
                usort($group['items'], static fn($a,$b) => [$a['start'],$a['post']] <=> [$b['start'],$b['post']]);
                echo '<section class="ccc-feed ccc-feed-' . (int) $feed['id'] . '" aria-label="' . esc_attr($feed['label']) . '">';
                if ($feed['show_label']) echo '<h3 class="ccc-feed-label">' . esc_html($feed['label']) . '</h3>';
                foreach ($group['items'] as $entry) echo '<div class="ccc-item jet-listing-grid__item jet-listing-dynamic-post-' . (int) $entry['post'] . '" data-post-id="' . (int) $entry['post'] . '">' . $entry['html'] . '</div>'; // Trusted published listing rendered by JetEngine.
                echo '</section>';
            }
            echo '</div></td>';
            if (6 === $i % 7) echo '</tr>';
        }
        echo '</tbody></table></div>';
        return ob_get_clean();
    }
}
