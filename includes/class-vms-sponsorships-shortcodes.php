<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Shortcodes {
    /** @var VMS_Sponsorships_Repository */
    private $repo;

    /** @var VMS_Sponsorships_Public_Renderer */
    private $renderer;

    public function __construct(VMS_Sponsorships_Repository $repo, VMS_Sponsorships_Public_Renderer $renderer) {
        $this->repo = $repo;
        $this->renderer = $renderer;

        add_shortcode('vms_sponsor_event', array($this, 'sponsor_event'));
        add_shortcode('vms_sponsor_slot', array($this, 'sponsor_slot'));
        add_shortcode('vms_sponsor_banner', array($this, 'sponsor_banner'));
        add_shortcode('vms_sponsor_placeholder', array($this, 'sponsor_placeholder'));
        add_shortcode('vms_sponsor_email', array($this, 'sponsor_email'));
        add_shortcode('vms_sponsor_season', array($this, 'sponsor_season'));
        add_action('template_redirect', array($this, 'handle_sponsor_redirect'));
        add_action('tribe_events_single_event_after_the_meta', array($this, 'render_automatic_event_page_banner'), 35);
    }

    public function sponsor_event($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'layout' => 'card',
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_event');

        return $this->renderer->render_event_slot(absint($atts['event_id']), sanitize_key($atts['slot']), array(
            'email_safe' => false,
            'layout' => sanitize_key($atts['layout']),
            'scope' => 'event',
            'show_placeholder' => 'yes' === strtolower($atts['show_placeholder']),
        ));
    }

    public function sponsor_slot($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'layout' => 'card',
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_slot');

        return $this->renderer->render_event_slot(absint($atts['event_id']), sanitize_key($atts['slot']), array(
            'email_safe' => false,
            'layout' => sanitize_key($atts['layout']),
            'scope' => 'event',
            'show_placeholder' => 'yes' === strtolower($atts['show_placeholder']),
        ));
    }

    public function sponsor_banner($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_banner');

        return $this->renderer->render_event_slot(absint($atts['event_id']), sanitize_key($atts['slot']), array(
            'email_safe' => false,
            'layout' => 'banner',
            'scope' => 'event',
            'show_placeholder' => 'yes' === strtolower($atts['show_placeholder']),
        ));
    }

    public function sponsor_email($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_email');

        return $this->renderer->render_event_slot(absint($atts['event_id']), sanitize_key($atts['slot']), array(
            'email_safe' => true,
            'layout' => 'card',
            'scope' => 'event',
            'show_placeholder' => 'yes' === strtolower($atts['show_placeholder']),
        ));
    }

    public function sponsor_placeholder($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'layout' => 'card',
            'type' => 'event',
            'slot' => 'presenting',
        ), $atts, 'vms_sponsor_placeholder');

        return $this->renderer->render_placeholder_slot(absint($atts['event_id']), sanitize_key($atts['slot']), array(
            'email_safe' => 'email' === sanitize_key($atts['type']),
            'layout' => sanitize_key($atts['layout']),
            'scope' => 'email' === sanitize_key($atts['type']) ? 'email' : 'event',
        ));
    }

    public function sponsor_season($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'card',
            'season_id' => 0,
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_season');

        $assignments = $this->repo->get_assignments(array(
            'season_id' => absint($atts['season_id']),
            'assignment_scope' => 'date_range',
            'slot_key' => sanitize_key($atts['slot']),
            'status__in' => array('confirmed', 'paid', 'fulfilled'),
            'public_display_enabled' => 1,
            'limit' => 1,
        ));

        if (!empty($assignments)) {
            return $this->renderer->render_assignment_record($assignments[0], array(
                'email_safe' => false,
                'layout' => sanitize_key($atts['layout']),
            ));
        }

        if ('yes' === strtolower($atts['show_placeholder'])) {
            return $this->renderer->render_placeholder_slot(0, sanitize_key($atts['slot']), array(
                'email_safe' => false,
                'layout' => sanitize_key($atts['layout']),
                'scope' => 'season',
            ));
        }

        return '';
    }

    public function render_automatic_event_page_banner() {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return;
        }

        $event_id = function_exists('get_queried_object_id') ? absint(get_queried_object_id()) : 0;
        if ($event_id <= 0 || get_post_type($event_id) !== 'tribe_events') {
            return;
        }

        $markup = $this->renderer->render_event_page_banner($event_id);
        if ($markup === '') {
            return;
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function handle_sponsor_redirect() {
        if (empty($_GET['vms_sponsor_redirect'])) {
            return;
        }

        $assignment = $this->repo->get_assignment(absint(wp_unslash($_GET['vms_sponsor_redirect'])));
        $destination_url = $assignment ? esc_url_raw((string) $assignment->sponsor_url) : '';
        if (!$assignment || $destination_url === '' || !wp_http_validate_url($destination_url)) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $this->repo->increment_metric($assignment->id, 'sponsor_click', $assignment->event_id, $assignment->season_id);
        wp_redirect($destination_url, 302, 'VMS Sponsorships');
        exit;
    }
}
