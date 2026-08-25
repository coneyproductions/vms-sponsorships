<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['test_hooks'] = array();
$GLOBALS['test_shortcodes'] = array();
$GLOBALS['test_event_id'] = 0;
$GLOBALS['test_commerce_mode'] = 'native';

function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['test_hooks'][$hook][$priority][] = $callback;
    return true;
}

function add_shortcode($tag, $callback) {
    $GLOBALS['test_shortcodes'][$tag] = $callback;
}

function do_action($hook) {
    $callbacks = $GLOBALS['test_hooks'][$hook] ?? array();
    ksort($callbacks, SORT_NUMERIC);
    foreach ($callbacks as $priority_callbacks) {
        foreach ($priority_callbacks as $callback) {
            call_user_func($callback);
        }
    }
}

function shortcode_atts($defaults, $atts) {
    return array_merge($defaults, is_array($atts) ? $atts : array());
}

function absint($value) {
    return abs((int) $value);
}

function sanitize_key($value) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function get_the_ID() {
    return (int) $GLOBALS['test_event_id'];
}

function get_queried_object_id() {
    return (int) $GLOBALS['test_event_id'];
}

function get_post_type($event_id) {
    return $event_id > 0 ? 'tribe_events' : '';
}

function is_admin() {
    return false;
}

function is_singular($post_type = '') {
    return $post_type === '' || $post_type === 'tribe_events';
}

function wp_enqueue_style($handle) {
}

function esc_attr($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_url($value) {
    return (string) $value;
}

function esc_url_raw($value) {
    return (string) $value;
}

function __($value) {
    return (string) $value;
}

function home_url($path = '/') {
    return 'https://example.test' . $path;
}

function add_query_arg($args, $url) {
    return $url . '?' . http_build_query($args);
}

function get_option($name, $default = false) {
    return $default;
}

function wp_hash($value) {
    return hash('sha256', (string) $value);
}

function get_transient($key) {
    return false;
}

function set_transient($key, $value, $expiration) {
    return true;
}

class Tribe__Settings_Manager {
    public static $commerce_hook = 'tribe_events_single_event_before_the_content';

    public static function get_option($key, $default = false) {
        return $key === 'ticket-commerce-form-location' ? self::$commerce_hook : $default;
    }
}

function vms_event_details_commerce_hook() {
    return Tribe__Settings_Manager::$commerce_hook;
}

function vms_event_details_before_commerce_priority() {
    return 4;
}

class VMS_Sponsorships_Repository {
    public function get_public_assignment($event_id, $slot) {
        return (object) array(
            'id' => 9000 + (int) $event_id,
            'event_id' => (int) $event_id,
            'season_id' => 0,
            'slot_key' => (string) $slot,
            'sponsor_display_name' => 'Compatibility Sponsor',
            'sponsor_tagline' => 'Sponsor before commerce',
            'sponsor_url' => '',
        );
    }

    public function get_approved_logo_url($assignment_id) {
        return '';
    }

    public function increment_metric($assignment_id, $metric, $event_id, $season_id) {
    }
}

require dirname(__DIR__) . '/includes/class-vms-sponsorships-shortcodes.php';

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$shortcodes = new VMS_Sponsorships_Shortcodes(new VMS_Sponsorships_Repository());
do_action('init');

$commerce_hook = Tribe__Settings_Manager::$commerce_hook;
$find_priority = static function ($hook, $class_name, $method_name) {
    foreach (($GLOBALS['test_hooks'][$hook] ?? array()) as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback) && is_object($callback[0] ?? null) && get_class($callback[0]) === $class_name && ($callback[1] ?? '') === $method_name) {
                return (int) $priority;
            }
        }
    }
    return null;
};

$assert(
    $find_priority($commerce_hook, 'VMS_Sponsorships_Shortcodes', 'render_automatic_event_page_banner') === 4,
    'Automatic sponsor placement is not registered at priority 4 on the configured commerce hook.'
);

add_action($commerce_hook, static function () {
    if ($GLOBALS['test_commerce_mode'] === 'native') {
        echo '<form data-test-native-commerce="1"></form>';
        return;
    }
    echo '<section data-vms-external-ticketing-panel="1"></section>';
}, 5);

$assert(count($GLOBALS['test_hooks'][$commerce_hook][5] ?? array()) === 1, 'Commerce is not registered at priority 5.');

$render_scenario = static function ($event_id, $commerce_mode, $manual_first) use ($shortcodes, $commerce_hook) {
    $GLOBALS['test_event_id'] = $event_id;
    $GLOBALS['test_commerce_mode'] = $commerce_mode;
    $GLOBALS['vms_sponsorships_event_page_banner_rendered'] = array();

    $markup = '';
    if ($manual_first) {
        $markup .= $shortcodes->sponsor_slot(array(
            'event_id' => $event_id,
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ));
    }

    ob_start();
    do_action($commerce_hook);
    $markup .= (string) ob_get_clean();
    return $markup;
};

$scenarios = array(
    'automatic native' => array(101, 'native', false),
    'manual then automatic native' => array(102, 'native', true),
    'automatic external' => array(103, 'external', false),
    'manual then automatic external' => array(104, 'external', true),
);

foreach ($scenarios as $label => $scenario) {
    [$event_id, $commerce_mode, $manual_first] = $scenario;
    $markup = $render_scenario($event_id, $commerce_mode, $manual_first);
    $sponsor_position = strpos($markup, 'data-vms-sponsor-assignment=');
    $native_position = strpos($markup, 'data-test-native-commerce="1"');
    $external_position = strpos($markup, 'data-vms-external-ticketing-panel="1"');
    $commerce_position = $commerce_mode === 'native' ? $native_position : $external_position;

    $assert(substr_count($markup, 'data-vms-sponsor-assignment=') === 1, $label . ': sponsor count is not exactly one.');
    $assert(substr_count($markup, 'data-test-native-commerce="1"') === ($commerce_mode === 'native' ? 1 : 0), $label . ': native commerce count is wrong.');
    $assert(substr_count($markup, 'data-vms-external-ticketing-panel="1"') === ($commerce_mode === 'external' ? 1 : 0), $label . ': external commerce count is wrong.');
    $assert($sponsor_position !== false && $commerce_position !== false && $sponsor_position < $commerce_position, $label . ': sponsor does not precede commerce.');
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "OK: Sponsorships 0.1.7.1 sponsor/commerce ordering and request-local duplicate guard.\n";
