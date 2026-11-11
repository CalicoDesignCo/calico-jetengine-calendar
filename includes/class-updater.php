<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

/** Stable public GitHub release assets through WordPress's native Update URI API. */
final class Updater {
    public const REPO = 'https://github.com/CalicoDesignCo/calico-jetengine-calendar';
    public const API = 'https://api.github.com/repos/CalicoDesignCo/calico-jetengine-calendar/releases/latest';
    public const SLUG = 'calico-elementor-calendar';
    public const ASSET = 'calico-jetengine-calendar.zip';
    public const CACHE = 'calico_jetengine_calendar_release_v1';
    private static string $plugin = '';

    public static function register(string $file): void {
        self::$plugin = plugin_basename($file);
        add_filter('update_plugins_github.com', [self::class, 'update'], 10, 4);
        add_filter('plugins_api', [self::class, 'details'], 10, 3);
        add_action('load-update-core.php', [self::class, 'refresh']);
        add_action('load-plugins.php', [self::class, 'refresh']);
    }

    public static function refresh(): void {
        if (isset($_GET['force-check']) && current_user_can('update_plugins')) delete_site_transient(self::CACHE);
    }

    public static function parse($release) {
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) || empty($release['id']) || !is_string($release['tag_name'] ?? null) || !preg_match('/^v?(\d+\.\d+\.\d+)$/D', $release['tag_name'], $matches)) return false;
        $tag = $release['tag_name'];
        $package = self::REPO . '/releases/download/' . $tag . '/' . self::ASSET;
        foreach ((array)($release['assets'] ?? []) as $asset) {
            if (!is_array($asset) || self::ASSET !== ($asset['name'] ?? '') || 'uploaded' !== ($asset['state'] ?? '') || (int)($asset['size'] ?? 0) < 1 || $package !== ($asset['browser_download_url'] ?? '')) continue;
            return ['version' => $matches[1], 'package' => $package, 'tag' => $tag];
        }
        return false;
    }

    public static function release() {
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached) && array_key_exists('release', $cached)) return $cached['release'];
        $response = wp_safe_remote_get(self::API, [
            'timeout' => 8, 'redirection' => 2, 'sslverify' => true, 'limit_response_size' => 524288,
            'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Calico-JetEngine-Calendar-Updater'],
        ]);
        $release = false;
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) $release = self::parse(json_decode(wp_remote_retrieve_body($response), true));
        set_site_transient(self::CACHE, ['release' => $release], $release ? 21600 : 900);
        return $release;
    }

    public static function update($update, $data, $file, $locales) {
        if (self::$plugin !== $file) return $update;
        $release = self::release();
        if (!$release || !version_compare($release['version'], $data['Version'] ?? '0', '>')) return false;
        return ['id' => self::REPO, 'slug' => self::SLUG, 'version' => $release['version'], 'url' => self::REPO . '/releases/tag/' . $release['tag'], 'package' => $release['package'], 'requires' => '6.5', 'requires_php' => '8.1'];
    }

    public static function details($result, $action, $args) {
        if ('plugin_information' !== $action || self::SLUG !== ($args->slug ?? '')) return $result;
        $release = self::release();
        if (!$release) return $result;
        return (object)[
            'name' => 'Calico JetEngine Calendar', 'slug' => self::SLUG, 'version' => $release['version'],
            'author' => 'Calico Design', 'homepage' => self::REPO, 'requires' => '6.5', 'requires_php' => '8.1',
            'download_link' => $release['package'],
            'sections' => ['description' => 'Combine up to four WordPress CPT feeds, each with its own JetEngine listing template and date fields, in a styled Elementor calendar.', 'changelog' => '<p>Read the versioned release notes on <a href="' . esc_url(self::REPO . '/releases/tag/' . $release['tag']) . '">GitHub</a>.</p>'],
        ];
    }
}
