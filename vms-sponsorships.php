<?php
/**
 * Plugin Name: VMS Sponsorships
 * Description: Premium VMS add-on for sponsor applications, packages, event sponsorship assignments, controlled banner inventory, public sponsor displays, and newsletter-safe shortcodes.
 * Version: 0.1.7.1
 * Author: VMS
 * Text Domain: vms-sponsorships
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VMS_SPONSORSHIPS_VERSION', '0.1.7.1');
define('VMS_SPONSORSHIPS_FILE', __FILE__);
define('VMS_SPONSORSHIPS_PATH', plugin_dir_path(__FILE__));
define('VMS_SPONSORSHIPS_URL', plugin_dir_url(__FILE__));

require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-install.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-repository.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-notifications.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-admin.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-shortcodes.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-public-forms.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships-event-plans.php';
require_once VMS_SPONSORSHIPS_PATH . 'includes/class-vms-sponsorships.php';

register_activation_hook(__FILE__, array('VMS_Sponsorships_Install', 'activate'));
register_deactivation_hook(__FILE__, array('VMS_Sponsorships_Install', 'deactivate'));

add_action('plugins_loaded', function () {
    VMS_Sponsorships::instance();
});
