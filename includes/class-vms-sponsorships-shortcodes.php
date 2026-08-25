<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Shortcodes {
    /** @var VMS_Sponsorships_Repository */
    private $repo;

    public function __construct(VMS_Sponsorships_Repository $repo) {
        $this->repo = $repo;

        add_shortcode('vms_sponsor_event', array($this, 'sponsor_event'));
        add_shortcode('vms_sponsor_slot', array($this, 'sponsor_slot'));
        add_shortcode('vms_sponsor_placeholder', array($this, 'sponsor_placeholder'));
        add_shortcode('vms_sponsor_email', array($this, 'sponsor_email'));
        add_shortcode('vms_sponsor_season', array($this, 'sponsor_season'));
        add_action('template_redirect', array($this, 'handle_sponsor_redirect'));
    }

    public function sponsor_event($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_event');

        return $this->render_assignment_or_placeholder(absint($atts['event_id']), sanitize_key($atts['slot']), 'yes' === strtolower($atts['show_placeholder']), false);
    }

    public function sponsor_slot($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_slot');

        return $this->render_assignment_or_placeholder(absint($atts['event_id']), sanitize_key($atts['slot']), 'yes' === strtolower($atts['show_placeholder']), false);
    }

    public function sponsor_email($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_email');

        return $this->render_assignment_or_placeholder(absint($atts['event_id']), sanitize_key($atts['slot']), 'yes' === strtolower($atts['show_placeholder']), true);
    }

    public function sponsor_placeholder($atts) {
        $atts = shortcode_atts(array(
            'event_id' => get_the_ID(),
            'type' => 'event',
            'slot' => 'presenting',
        ), $atts, 'vms_sponsor_placeholder');

        return $this->render_placeholder(absint($atts['event_id']), sanitize_key($atts['slot']), 'email' === sanitize_key($atts['type']));
    }

    public function sponsor_season($atts) {
        $atts = shortcode_atts(array(
            'season_id' => 0,
            'slot' => 'presenting',
            'show_placeholder' => 'yes',
        ), $atts, 'vms_sponsor_season');

        $assignments = $this->repo->get_assignments(array(
            'season_id' => absint($atts['season_id']),
            'slot_key' => sanitize_key($atts['slot']),
            'public_display_enabled' => 1,
            'limit' => 1,
        ));

        if (!empty($assignments) && in_array($assignments[0]->status, array('confirmed', 'paid', 'fulfilled'), true)) {
            return $this->render_assignment($assignments[0], false);
        }

        if ('yes' === strtolower($atts['show_placeholder'])) {
            return $this->render_placeholder(0, sanitize_key($atts['slot']), false, 'season');
        }

        return '';
    }

    private function render_assignment_or_placeholder($event_id, $slot, $show_placeholder, $email_safe) {
        if (!$event_id) {
            return '';
        }

        $assignment = $this->repo->get_public_assignment($event_id, $slot);
        if ($assignment) {
            $this->maybe_track_impression($assignment, $email_safe);
            return $this->render_assignment($assignment, $email_safe);
        }

        return $show_placeholder ? $this->render_placeholder($event_id, $slot, $email_safe) : '';
    }

    private function render_assignment($assignment, $email_safe = false) {
        $logo_url = $this->repo->get_approved_logo_url($assignment->id);
        $has_link = !empty($assignment->sponsor_url);
        $link_url = $has_link ? $this->assignment_link($assignment, $email_safe) : '';
        $classes = $email_safe ? 'vms-sponsor-block vms-sponsor-block--email' : 'vms-sponsor-block';

        if (!$email_safe) {
            wp_enqueue_style('vms-sponsorships-public');
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-vms-sponsor-assignment="<?php echo esc_attr($assignment->id); ?>">
            <div class="vms-sponsor-block__eyebrow"><?php echo esc_html($this->slot_label($assignment->slot_key)); ?></div>
            <?php if ($logo_url) : ?>
                <div class="vms-sponsor-block__logo-wrap">
                    <?php if ($has_link) : ?>
                        <a href="<?php echo esc_url($link_url); ?>" target="_blank" rel="noopener noreferrer sponsored">
                    <?php endif; ?>
                    <img class="vms-sponsor-block__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($assignment->sponsor_display_name); ?>">
                    <?php if ($has_link) : ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="vms-sponsor-block__content">
                <strong class="vms-sponsor-block__name">
                    <?php if ($has_link && !$logo_url) : ?><a href="<?php echo esc_url($link_url); ?>" target="_blank" rel="noopener noreferrer sponsored"><?php endif; ?>
                    <?php echo esc_html($assignment->sponsor_display_name); ?>
                    <?php if ($has_link && !$logo_url) : ?></a><?php endif; ?>
                </strong>
                <?php if (!empty($assignment->sponsor_tagline)) : ?>
                    <div class="vms-sponsor-block__tagline"><?php echo esc_html($assignment->sponsor_tagline); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return trim(ob_get_clean());
    }

    private function render_placeholder($event_id, $slot, $email_safe = false, $scope = 'event') {
        if (!$email_safe) {
            wp_enqueue_style('vms-sponsorships-public');
        }

        $heading = get_option('vms_sponsorships_placeholder_heading', 'Your business could sponsor this show');
        $body = get_option('vms_sponsorships_placeholder_body', 'Put your brand in front of East Texas music fans before, during, and after the event.');
        $button = get_option('vms_sponsorships_placeholder_button', 'Sponsor this event');
        $url = get_option('vms_sponsorships_inquiry_page_url', '');

        if (!$url) {
            $url = $this->built_in_application_url($event_id, $slot, $scope);
        } else {
            $url = add_query_arg(array(
                'vms_sponsor_event_id' => absint($event_id),
                'vms_sponsor_slot' => sanitize_key($slot),
                'vms_sponsor_scope' => sanitize_key($scope),
            ), $url);
        }

        $classes = $email_safe ? 'vms-sponsor-placeholder vms-sponsor-placeholder--email' : 'vms-sponsor-placeholder';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-vms-sponsor-placeholder="<?php echo esc_attr($slot); ?>">
            <div class="vms-sponsor-placeholder__eyebrow"><?php echo esc_html($this->slot_label($slot)); ?></div>
            <strong class="vms-sponsor-placeholder__heading"><?php echo esc_html($heading); ?></strong>
            <div class="vms-sponsor-placeholder__body"><?php echo esc_html($body); ?></div>
            <a class="vms-sponsor-placeholder__button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($button); ?></a>
        </div>
        <?php
        return trim(ob_get_clean());
    }

    public function handle_sponsor_redirect() {
        if (empty($_GET['vms_sponsor_redirect'])) {
            return;
        }

        $assignment = $this->repo->get_assignment(absint($_GET['vms_sponsor_redirect']));
        if (!$assignment || empty($assignment->sponsor_url)) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $this->repo->increment_metric($assignment->id, 'sponsor_click', $assignment->event_id, $assignment->season_id);
        wp_redirect(esc_url_raw($assignment->sponsor_url));
        exit;
    }

    private function tracked_link($assignment) {
        return add_query_arg(array(
            'vms_sponsor_redirect' => absint($assignment->id),
        ), home_url('/'));
    }

    private function assignment_link($assignment, $email_safe) {
        if ($email_safe) {
            return esc_url_raw($assignment->sponsor_url);
        }

        return $this->tracked_link($assignment);
    }

    private function built_in_application_url($event_id, $slot, $scope) {
        return add_query_arg(array(
            'vms_sponsor_apply' => 1,
            'vms_sponsor_event_id' => absint($event_id),
            'vms_sponsor_slot' => sanitize_key($slot),
            'vms_sponsor_scope' => sanitize_key($scope),
        ), home_url('/'));
    }

    private function maybe_track_impression($assignment, $email_safe) {
        if ($email_safe) {
            return;
        }

        $transient_key = 'vms_sponsor_impression_' . absint($assignment->id) . '_' . wp_hash($_SERVER['REMOTE_ADDR'] ?? '') . '_' . gmdate('YmdH');
        if (get_transient($transient_key)) {
            return;
        }

        set_transient($transient_key, 1, HOUR_IN_SECONDS);
        $this->repo->increment_metric($assignment->id, 'sponsor_impression', $assignment->event_id, $assignment->season_id);
    }

    private function slot_label($slot) {
        $labels = array(
            'presenting' => __('Presented by', 'vms-sponsorships'),
            'bar' => __('Bar sponsor', 'vms-sponsorships'),
            'veterans' => __('Veterans admission sponsor', 'vms-sponsorships'),
            'kids' => __('Kids admission sponsor', 'vms-sponsorships'),
            'food_truck' => __('Food truck sponsor', 'vms-sponsorships'),
            'supporting' => __('Supporting sponsor', 'vms-sponsorships'),
        );

        return $labels[$slot] ?? ucwords(str_replace('_', ' ', $slot));
    }
}
