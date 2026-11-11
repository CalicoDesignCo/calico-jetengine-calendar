<?php
namespace Calico\Calendar;

defined('ABSPATH') || exit;

abstract class Occurrence_Tag extends \Elementor\Core\DynamicTags\Tag {
    protected string $edge = 'start';
    public function get_name() { return 'calico-calendar-occurrence-' . $this->edge; }
    public function get_title() { return 'start' === $this->edge ? __('Calendar Occurrence Start', 'calico-elementor-calendar') : __('Calendar Occurrence End', 'calico-elementor-calendar'); }
    public function get_group() { return 'calico-calendar'; }
    public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }
    protected function register_controls() {
        $this->add_control('format', ['label' => __('Date format', 'calico-elementor-calendar'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'g:i a', 'description' => __('PHP date format, for example F j or g:i a. Uses the occurrence currently rendered by this calendar.', 'calico-elementor-calendar')]);
    }
    public function render() {
        $date = Engine::$occurrence[$this->edge] ?? null;
        if (!$date instanceof \DateTimeImmutable) return;
        $format = $this->get_settings('format') ?: 'g:i a';
        echo esc_html(wp_date($format, $date->getTimestamp(), wp_timezone()));
    }
}

final class Occurrence_Start extends Occurrence_Tag {}
final class Occurrence_End extends Occurrence_Tag { protected string $edge = 'end'; }
