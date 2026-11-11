<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

final class Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'calico-multi-feed-calendar'; }
    public function get_title() { return __('Calico JetEngine Calendar', 'calico-elementor-calendar'); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return ['general']; }
    public function get_keywords() { return ['calendar', 'calico', 'jetengine', 'events', 'hours']; }
    public function get_script_depends() { return ['calico-elementor-calendar']; }
    public function get_style_depends() { return ['calico-elementor-calendar']; }
    protected function is_dynamic_content(): bool { return true; }

    private function control(string $name, string $label, string $type, array $options = []): void {
        $this->add_control($name, array_merge(['label' => $label, 'type' => $type], $options));
    }

    private function dimensions(string $name, string $label, string $selector, string $property): void {
        $this->add_responsive_control($name, ['label' => $label, 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => [$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
    }

    private function color(string $name, string $label, string $selector, string $property): void {
        $this->control($name, $label, \Elementor\Controls_Manager::COLOR, ['selectors' => [$selector => $property . ': {{VALUE}};']]);
    }

    /** Defaults remain empty so existing calendars inherit their saved shared navigation styles. */
    private function navigation_styles(string $prefix, string $selector, bool $arrow = false): void {
        $cm = \Elementor\Controls_Manager::class;
        $this->color($prefix . '_color', __('Text / icon color', 'calico-elementor-calendar'), $selector, 'color');
        $this->color($prefix . '_background', __('Background', 'calico-elementor-calendar'), $selector, 'background-color');
        $this->color($prefix . '_hover_color', __('Hover text / icon', 'calico-elementor-calendar'), $selector . ':hover:not(:disabled)', 'color');
        $this->color($prefix . '_hover_background', __('Hover background', 'calico-elementor-calendar'), $selector . ':hover:not(:disabled)', 'background-color');
        $this->color($prefix . '_focus_color', __('Focus text / icon', 'calico-elementor-calendar'), $selector . ':focus-visible', 'color');
        $this->color($prefix . '_focus_background', __('Focus background', 'calico-elementor-calendar'), $selector . ':focus-visible', 'background-color');
        $this->color($prefix . '_disabled_color', __('Disabled text / icon', 'calico-elementor-calendar'), $selector . ':disabled', 'color');
        $this->color($prefix . '_disabled_background', __('Disabled background', 'calico-elementor-calendar'), $selector . ':disabled', 'background-color');
        if ($arrow) {
            $this->add_responsive_control($prefix . '_size', ['label' => __('Arrow size', 'calico-elementor-calendar'), 'type' => $cm::SLIDER, 'size_units' => ['px', 'em'], 'range' => ['px' => ['min' => 8, 'max' => 100], 'em' => ['min' => .5, 'max' => 5, 'step' => .1]], 'selectors' => [$selector => 'font-size: {{SIZE}}{{UNIT}};']]);
        } else {
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => $prefix . '_typography', 'selector' => $selector]);
        }
        foreach (['width' => __('Width', 'calico-elementor-calendar'), 'height' => __('Minimum height', 'calico-elementor-calendar')] as $key => $label) {
            $property = 'height' === $key ? 'min-height' : 'width';
            $this->add_responsive_control($prefix . '_' . $key, ['label' => $label, 'type' => $cm::SLIDER, 'size_units' => ['px', 'em'], 'range' => ['px' => ['min' => 0, 'max' => 400], 'em' => ['min' => 0, 'max' => 25, 'step' => .1]], 'selectors' => [$selector => $property . ': {{SIZE}}{{UNIT}};' . ('width' === $key ? ' min-width: 0;' : '')]]);
        }
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => $prefix . '_border', 'selector' => $selector]);
        $this->color($prefix . '_hover_border_color', __('Hover border color', 'calico-elementor-calendar'), $selector . ':hover:not(:disabled)', 'border-color');
        $this->color($prefix . '_focus_border_color', __('Focus border color', 'calico-elementor-calendar'), $selector . ':focus-visible', 'border-color');
        $this->dimensions($prefix . '_radius', __('Corner radius', 'calico-elementor-calendar'), $selector, 'border-radius');
        $this->dimensions($prefix . '_padding', __('Padding', 'calico-elementor-calendar'), $selector, 'padding');
    }

    protected function register_controls() {
        $cm = \Elementor\Controls_Manager::class;
        $types = [];
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if (!in_array($type->name, ['attachment', 'elementor_library', 'jet-engine'], true)) $types[$type->name] = $type->labels->singular_name . ' (' . $type->name . ')';
        }
        // Include private/admin-only JetEngine CPTs; their individual posts must still be published.
        foreach (get_post_types(['_builtin' => false], 'objects') as $type) {
            if (!in_array($type->name, ['elementor_library', 'jet-engine'], true)) $types[$type->name] = $type->labels->singular_name . ' (' . $type->name . ')';
        }
        $listings = ['' => __('Choose a listing', 'calico-elementor-calendar')];
        foreach (get_posts(['post_type' => 'jet-engine', 'post_status' => 'publish', 'has_password' => false, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $listing) $listings[$listing->ID] = $listing->post_title . ' (#' . $listing->ID . ')';
        $this->start_controls_section('calendar', ['label' => __('Calendar', 'calico-elementor-calendar')]);
        $this->control('calendar_label', __('Accessible calendar name', 'calico-elementor-calendar'), $cm::TEXT, ['default' => __('Calendar', 'calico-elementor-calendar')]);
        $days = [];
        for ($d=0; $d<7; $d++) $days[$d] = wp_date('l', strtotime('2024-01-07 +' . $d . ' days'), new \DateTimeZone('UTC'));
        $this->control('week_start', __('Week starts on', 'calico-elementor-calendar'), $cm::SELECT, ['options' => $days, 'default' => (string) get_option('start_of_week', 1)]);
        $this->control('min_year', __('First navigation year', 'calico-elementor-calendar'), $cm::NUMBER, ['default' => 2020, 'min' => 1970, 'max' => 2099]);
        $this->control('max_year', __('Last navigation year', 'calico-elementor-calendar'), $cm::NUMBER, ['default' => 2040, 'min' => 1970, 'max' => 2099]);
        $this->control('nearby', __('Show entries on adjacent-month days', 'calico-elementor-calendar'), $cm::SWITCHER, ['default' => 'yes']);
        $this->control('hide_past', __('Hide entries from earlier dates', 'calico-elementor-calendar'), $cm::SWITCHER);
        $this->control('mobile_view', __('Mobile layout', 'calico-elementor-calendar'), $cm::SELECT, ['default' => 'agenda', 'options' => ['agenda' => __('Agenda', 'calico-elementor-calendar'), 'grid' => __('Month grid', 'calico-elementor-calendar')]]);
        $this->control('cache_seconds', __('Query cache (seconds)', 'calico-elementor-calendar'), $cm::NUMBER, ['default' => 60, 'min' => 0, 'max' => 3600, 'description' => __('Caches matching post IDs. Saving content invalidates the cache. Zero disables it.', 'calico-elementor-calendar')]);
        $this->end_controls_section();
        for ($i=1; $i<=Engine::MAX_FEEDS; $i++) {
            $p = 'feed_' . $i . '_';
            $this->start_controls_section($p . 'section', ['label' => sprintf(__('Feed %d', 'calico-elementor-calendar'), $i)]);
            $this->control($p.'enabled', __('Enable this feed', 'calico-elementor-calendar'), $cm::SWITCHER);
            $this->control($p.'label', __('Feed label', 'calico-elementor-calendar'), $cm::TEXT, ['default' => 'Feed ' . $i]);
            $this->control($p.'post_type', __('Post type', 'calico-elementor-calendar'), $cm::SELECT2, ['options' => $types]);
            $this->control($p.'listing', __('JetEngine listing template', 'calico-elementor-calendar'), $cm::SELECT2, ['options' => $listings]);
            $this->control($p.'start_key', __('Start date meta key', 'calico-elementor-calendar'), $cm::TEXT, ['placeholder' => 'advanced_date']);
            $this->control($p.'end_key', __('End date meta key', 'calico-elementor-calendar'), $cm::TEXT, ['description' => __('Optional. In JetEngine mode, blank uses the start key plus __end_date.', 'calico-elementor-calendar')]);
            $this->control($p.'storage', __('Date storage', 'calico-elementor-calendar'), $cm::SELECT, ['default' => 'jetengine', 'options' => ['jetengine' => __('JetEngine local timestamp / Advanced Date', 'calico-elementor-calendar'), 'unix' => __('Unix timestamp (actual instant)', 'calico-elementor-calendar'), 'text' => __('ISO date / date-time text', 'calico-elementor-calendar')]]);
            $this->control($p.'span', __('Show on every date in its range', 'calico-elementor-calendar'), $cm::SWITCHER, ['default' => 'yes', 'description' => __('Disable to show each occurrence only on its starting date.', 'calico-elementor-calendar')]);
            $this->control($p.'inclusive_end', __('Include a midnight end date', 'calico-elementor-calendar'), $cm::SWITCHER, ['description' => __('Enable for date-only ranges where the ending date is included. Leave off for timed events ending at midnight.', 'calico-elementor-calendar')]);
            $this->control($p.'overnight', __('Keep overnight entries on starting date', 'calico-elementor-calendar'), $cm::SWITCHER, ['description' => __('For an end before noon on the next day, calendar display ends at 11:59 PM on the starting day. Stored dates and event copy remain unchanged.', 'calico-elementor-calendar')]);
            $this->control($p.'order', __('Feed order within each day', 'calico-elementor-calendar'), $cm::NUMBER, ['default' => $i, 'min' => -100, 'max' => 100]);
            $this->control($p.'show_label', __('Show feed label', 'calico-elementor-calendar'), $cm::SWITCHER);
            $this->control($p.'limit', __('Maximum matching posts per month', 'calico-elementor-calendar'), $cm::NUMBER, ['default' => 200, 'min' => 1, 'max' => 500]);
            $this->control($p.'taxonomy', __('Filter taxonomy slug', 'calico-elementor-calendar'), $cm::TEXT, ['description' => __('Optional; use with term slugs below.', 'calico-elementor-calendar')]);
            $this->control($p.'terms', __('Term slugs (comma separated)', 'calico-elementor-calendar'), $cm::TEXT);
            $this->control($p.'meta_key', __('Filter meta key', 'calico-elementor-calendar'), $cm::TEXT);
            $this->control($p.'meta_value', __('Filter exact meta value', 'calico-elementor-calendar'), $cm::TEXT);
            $this->control($p.'include_ids', __('Include post IDs', 'calico-elementor-calendar'), $cm::TEXT, ['description' => __('Optional comma-separated IDs. Only these posts can appear in this feed.', 'calico-elementor-calendar')]);
            $this->control($p.'exclude_ids', __('Exclude post IDs', 'calico-elementor-calendar'), $cm::TEXT, ['description' => __('Optional comma-separated IDs. Useful for keeping an ongoing post out of the dated-events feed.', 'calico-elementor-calendar')]);
            $this->end_controls_section();
        }
        $this->start_controls_section('appearance', ['label' => __('Calendar appearance', 'calico-elementor-calendar'), 'tab' => $cm::TAB_STYLE]);
        foreach ([
            'border_color' => ['Default grid lines', '--ccc-border'],
            'nav_background' => ['Navigation background', '--ccc-nav-bg'], 'nav_color' => ['Navigation text', '--ccc-nav-color'],
        ] as $key => $style) $this->control($key, __($style[0], 'calico-elementor-calendar'), $cm::COLOR, ['selectors' => ['{{WRAPPER}} .ccc-calendar' => $style[1] . ': {{VALUE}};']]);
        $this->add_responsive_control('item_gap', ['label' => __('Entry spacing', 'calico-elementor-calendar'), 'type' => $cm::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 80]], 'selectors' => ['{{WRAPPER}} .ccc-calendar' => '--ccc-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'navigation_typography', 'label' => __('Navigation typography', 'calico-elementor-calendar'), 'selector' => '{{WRAPPER}} .ccc-nav']);
        $this->end_controls_section();

        $this->start_controls_section('grid_appearance', ['label' => __('Grid outer border', 'calico-elementor-calendar'), 'tab' => $cm::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'grid_outline', 'selector' => '{{WRAPPER}} .ccc-grid']);
        $this->dimensions('grid_radius', __('Outer corner radius', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-grid', 'border-radius');
        $this->color('grid_background', __('Grid background / day gaps', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-grid', 'background-color');
        $this->add_responsive_control('day_gap', ['label' => __('Space between days', 'calico-elementor-calendar'), 'type' => $cm::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => ['{{WRAPPER}} .ccc-table' => 'border-collapse: separate; border-spacing: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .ccc-calendar' => '--ccc-day-gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('days_appearance', ['label' => __('Days', 'calico-elementor-calendar'), 'tab' => $cm::TAB_STYLE]);
        foreach ([
            'day_background' => ['Day background', '--ccc-day-bg'], 'today_background' => ['Today background', '--ccc-today-bg'],
            'date_color' => ['Date text', '--ccc-date-color'],
        ] as $key => $style) $this->color($key, __($style[0], 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-calendar', $style[1]);
        $this->color('day_hover_background', __('Day hover background', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table .ccc-day:hover', 'background-color');
        $this->color('adjacent_day_background', __('Adjacent-month day background', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table .ccc-other-month', 'background-color');
        $this->color('adjacent_date_color', __('Adjacent-month date text', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-other-month .ccc-day-header', 'color');
        $this->color('today_date_color', __('Today date text', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-today .ccc-day-header', 'color');
        $this->add_responsive_control('day_radius', ['label' => __('Day corner radius', 'calico-elementor-calendar'), 'type' => $cm::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => ['{{WRAPPER}} .ccc-table .ccc-day' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};', '{{WRAPPER}} .ccc-table' => 'border-collapse: separate; border-spacing: var(--ccc-day-gap, 0px);']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'day_border', 'selector' => '{{WRAPPER}} .ccc-table .ccc-day']);
        $this->dimensions('day_padding', __('Day padding', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table .ccc-day', 'padding');
        $this->add_responsive_control('day_height', ['label' => __('Minimum day height', 'calico-elementor-calendar'), 'type' => $cm::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 600]], 'selectors' => ['{{WRAPPER}} .ccc-day-content' => 'min-height: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'dates_typography', 'label' => __('Date typography', 'calico-elementor-calendar'), 'selector' => '{{WRAPPER}} .ccc-day-header']);
        $this->color('date_badge_background', __('Date badge background', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-day-header time', 'background-color');
        $this->dimensions('date_badge_radius', __('Date badge radius', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-day-header time', 'border-radius');
        $this->dimensions('date_badge_padding', __('Date badge padding', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-day-header time', 'padding');
        $this->end_controls_section();

        $this->start_controls_section('mobile_day_heading', ['label' => __('Mobile day heading', 'calico-elementor-calendar'), 'tab' => $cm::TAB_STYLE]);
        // Elementor's device prefix scopes generated CSS to the configured mobile breakpoint.
        // Empty controls emit no CSS, preserving all existing date badge settings.
        $mobile_heading = '(mobile){{WRAPPER}} .ccc-mobile-agenda .ccc-day-header time';
        $this->color('mobile_day_heading_background', __('Background', 'calico-elementor-calendar'), $mobile_heading, 'background-color');
        $this->color('mobile_day_heading_color', __('Text color', 'calico-elementor-calendar'), $mobile_heading, 'color');
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'mobile_day_heading_typography', 'label' => __('Typography / text size', 'calico-elementor-calendar'), 'selector' => $mobile_heading]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'mobile_day_heading_border', 'selector' => $mobile_heading]);
        foreach (['padding' => __('Padding', 'calico-elementor-calendar'), 'radius' => __('Corner radius', 'calico-elementor-calendar')] as $part => $label) {
            $this->control('mobile_day_heading_' . $part, $label, $cm::DIMENSIONS, ['size_units' => ['px', 'em', '%'], 'selectors' => [$mobile_heading => ('radius' === $part ? 'border-radius' : 'padding') . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        }
        $this->end_controls_section();

        $this->start_controls_section('weekdays_appearance', ['label' => __('Weekday headings', 'calico-elementor-calendar'), 'tab' => $cm::TAB_STYLE]);
        $this->color('weekday_background', __('Background', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table th', 'background-color');
        $this->color('weekday_text_color', __('Text color', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table th', 'color');
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'weekday_typography', 'selector' => '{{WRAPPER}} .ccc-table th']);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'weekday_border', 'selector' => '{{WRAPPER}} .ccc-table th']);
        $this->dimensions('weekday_padding', __('Padding', 'calico-elementor-calendar'), '{{WRAPPER}} .ccc-table th', 'padding');
        $this->end_controls_section();

        foreach (['month' => __('Month selector', 'calico-elementor-calendar'), 'year' => __('Year selector', 'calico-elementor-calendar')] as $part => $label) {
            $this->start_controls_section($part . '_appearance', ['label' => $label, 'tab' => $cm::TAB_STYLE]);
            $this->navigation_styles($part, '{{WRAPPER}} .ccc-nav select[data-ccc-' . $part . ']');
            $this->end_controls_section();
        }
        foreach (['arrows' => __('Arrows — shared', 'calico-elementor-calendar'), 'previous_arrow' => __('Previous arrow', 'calico-elementor-calendar'), 'next_arrow' => __('Next arrow', 'calico-elementor-calendar')] as $part => $label) {
            $selector = '{{WRAPPER}} .ccc-nav button';
            if ('previous_arrow' === $part) $selector .= '[data-ccc-step="-1"]';
            if ('next_arrow' === $part) $selector .= '[data-ccc-step="1"]';
            $this->start_controls_section($part . '_appearance', ['label' => $label, 'tab' => $cm::TAB_STYLE]);
            $this->navigation_styles($part, $selector, true);
            $this->end_controls_section();
        }
        for ($i=1; $i<=Engine::MAX_FEEDS; $i++) {
            $selector = '{{WRAPPER}} .ccc-feed-' . $i;
            $this->start_controls_section('feed_style_'.$i, ['label' => sprintf(__('Feed %d appearance', 'calico-elementor-calendar'), $i), 'tab' => $cm::TAB_STYLE]);
            $this->control('feed_bg_'.$i, __('Background', 'calico-elementor-calendar'), $cm::COLOR, ['selectors' => [$selector => 'background-color: {{VALUE}};']]);
            $this->control('feed_label_color_'.$i, __('Label color', 'calico-elementor-calendar'), $cm::COLOR, ['selectors' => [$selector.' .ccc-feed-label' => 'color: {{VALUE}};']]);
            $this->add_responsive_control('feed_padding_'.$i, ['label' => __('Padding', 'calico-elementor-calendar'), 'type' => $cm::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'feed_border_'.$i, 'selector' => $selector]);
            $this->end_controls_section();
        }
    }

    protected function render() {
        if (!Plugin::ready()) return;
        $config = Engine::normalize($this->get_settings_for_display());
        if (!$config['feeds']) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) echo '<p>' . esc_html__('Enable a feed and select its post type, listing template, and start date field.', 'calico-elementor-calendar') . '</p>';
            return;
        }
        $document = \Elementor\Plugin::$instance->documents->get_current();
        $document_id = $document ? $document->get_main_id() : get_the_ID();
        $month = wp_date('Y-m');
        if ((int) substr($month, 0, 4) < $config['min_year']) $month = $config['min_year'] . '-01';
        if ((int) substr($month, 0, 4) > $config['max_year']) $month = $config['max_year'] . '-12';
        $payload = ['url' => admin_url('admin-ajax.php'), 'document' => $document_id, 'widget' => $this->get_id(), 'token' => Plugin::token($document_id, $this->get_id(), $config), 'month' => $month, 'min' => $config['min_year'], 'max' => $config['max_year'], 'error' => __('Unable to load that month. Please try again or reload the page.', 'calico-elementor-calendar')];
        echo '<div class="ccc-calendar ccc-mobile-' . esc_attr($config['mobile']) . '" data-ccc="' . esc_attr(wp_json_encode($payload)) . '" aria-label="' . esc_attr($config['title']) . '"><div class="ccc-inner">';
        try { echo Engine::inner($config, $month); }
        catch (\Throwable $error) { echo '<p role="alert">' . esc_html__('Unable to load the calendar. Please check its feed settings.', 'calico-elementor-calendar') . '</p>'; }
        echo '</div><p class="ccc-error" role="alert" hidden></p></div>';
    }
}
