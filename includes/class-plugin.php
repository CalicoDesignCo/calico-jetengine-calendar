<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

final class Plugin {
    private static bool $invalidated = false;

    public static function boot(): void {
        add_action('admin_notices', [self::class, 'notice']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('elementor/frontend/after_register_scripts', [self::class, 'assets']);
        add_action('elementor/widgets/register', [self::class, 'widgets']);
        add_action('elementor/dynamic_tags/register', [self::class, 'tags']);
        add_action('wp_ajax_calico_calendar_month', [self::class, 'ajax']);
        add_action('wp_ajax_nopriv_calico_calendar_month', [self::class, 'ajax']);
        foreach (['save_post', 'deleted_post', 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'set_object_terms'] as $hook) add_action($hook, [self::class, 'invalidate']);
    }

    public static function ready(): bool {
        return defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.20', '>=') && function_exists('jet_engine') && isset(jet_engine()->listings, jet_engine()->frontend);
    }

    public static function notice(): void {
        if (!current_user_can('activate_plugins') || self::ready()) return;
        echo '<div class="notice notice-warning"><p>' . esc_html__('Calico JetEngine Calendar requires Elementor 3.20 or later and JetEngine with Listings enabled.', 'calico-elementor-calendar') . '</p></div>';
    }

    public static function assets(): void {
        wp_register_style('calico-elementor-calendar', CALICO_CALENDAR_URL . 'assets/calendar.css', [], CALICO_CALENDAR_VERSION);
        wp_register_script('calico-elementor-calendar', CALICO_CALENDAR_URL . 'assets/calendar.js', ['elementor-frontend'], CALICO_CALENDAR_VERSION, true);
    }

    public static function widgets($manager): void {
        if (!self::ready()) return;
        require_once CALICO_CALENDAR_PATH . 'includes/class-widget.php';
        $manager->register(new Widget());
    }

    public static function tags($manager): void {
        if (!self::ready()) return;
        require_once CALICO_CALENDAR_PATH . 'includes/class-occurrence-tag.php';
        $manager->register_group('calico-calendar', ['title' => __('Calico Calendar', 'calico-elementor-calendar')]);
        $manager->register(new Occurrence_Start());
        $manager->register(new Occurrence_End());
    }

    public static function invalidate(): void {
        if (self::$invalidated) return;
        self::$invalidated = true;
        update_option('calico_calendar_cache_version', wp_generate_uuid4(), false);
    }

    public static function token(int $document, string $widget, array $config): string {
        return hash_hmac('sha256', wp_json_encode([$document, $widget, $config]), wp_salt('auth'));
    }

    public static function find_widget(array $elements, string $id): ?array {
        foreach ($elements as $element) {
            if (!is_array($element)) continue;
            if (($element['id'] ?? '') === $id && ($element['widgetType'] ?? '') === 'calico-multi-feed-calendar') return $element;
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $found = self::find_widget($element['elements'], $id);
                if ($found) return $found;
            }
        }
        return null;
    }

    /** Public reads are tied to a published document and its saved widget configuration. */
    public static function saved_config(int $document, string $widget): ?array {
        $post = get_post($document);
        if (!$post || $post->post_password) return null;
        if ('publish' !== $post->post_status && !current_user_can('edit_post', $document)) return null;
        $data = get_post_meta($document, '_elementor_data', true);
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return null;
        $element = self::find_widget($data, $widget);
        return $element ? Engine::normalize(is_array($element['settings'] ?? null) ? $element['settings'] : []) : null;
    }

    public static function ajax(): void {
        if (!self::ready()) wp_send_json_error(['message' => __('Calendar is unavailable.', 'calico-elementor-calendar')], 503);
        $request = wp_unslash($_POST);
        $document = absint($request['document'] ?? 0);
        $widget = is_string($request['widget'] ?? null) ? $request['widget'] : '';
        $month = is_string($request['month'] ?? null) ? $request['month'] : '';
        $token = is_string($request['token'] ?? null) ? $request['token'] : '';
        if (!$document || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $widget) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) || !preg_match('/^[a-f0-9]{64}$/D', $token)) wp_send_json_error(['message' => __('Invalid calendar request.', 'calico-elementor-calendar')], 400);
        $config = self::saved_config($document, $widget);
        if (!$config || !hash_equals(self::token($document, $widget, $config), $token)) wp_send_json_error(['message' => __('This calendar has changed. Please reload the page.', 'calico-elementor-calendar')], 403);
        try {
            $html = Engine::inner($config, $month);
            // Listing templates may enqueue additional Elementor or JetEngine assets during rendering.
            ob_start();
            wp_print_styles();
            wp_print_scripts();
            wp_print_footer_scripts();
            $assets = ob_get_clean();
            wp_send_json_success(['html' => $html, 'assets' => $assets, 'month' => $month]);
        } catch (\Throwable $error) {
            wp_send_json_error(['message' => __('Unable to load that month. Please try again.', 'calico-elementor-calendar')], 400);
        }
    }
}
