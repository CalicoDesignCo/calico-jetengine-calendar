<?php
/**
 * Plugin Name: Calico JetEngine Calendar
 * Description: One Elementor calendar with up to four independent JetEngine CPT feeds, listing templates, and date fields.
 * Version: 1.0.0
 * Plugin URI: https://github.com/CalicoDesignCo/calico-jetengine-calendar
 * Update URI: https://github.com/CalicoDesignCo/calico-jetengine-calendar
 * Author: Calico Design
 * License: GPL-2.0-or-later
 * Text Domain: calico-elementor-calendar
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;
define('CALICO_CALENDAR_VERSION', '1.0.0');
define('CALICO_CALENDAR_PATH', plugin_dir_path(__FILE__));
define('CALICO_CALENDAR_URL', plugin_dir_url(__FILE__));
require_once CALICO_CALENDAR_PATH . 'includes/class-dates.php';
require_once CALICO_CALENDAR_PATH . 'includes/class-engine.php';
require_once CALICO_CALENDAR_PATH . 'includes/class-plugin.php';
require_once CALICO_CALENDAR_PATH . 'includes/class-updater.php';
\Calico\Calendar\Updater::register(__FILE__);
add_action('plugins_loaded', ['Calico\Calendar\Plugin', 'boot'], 30);
