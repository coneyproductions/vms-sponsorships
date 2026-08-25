<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Event_Plans {
    /** @var VMS_Sponsorships_Repository */
    private $repo;

    public function __construct(VMS_Sponsorships_Repository $repo) {
        $this->repo = $repo;

        add_action('add_meta_boxes', array($this, 'add_event_plan_meta_box'));
        add_action('save_post', array($this, 'save_event_plan_meta'), 10, 2);
        add_action('vms_event_plan_after_modules', array($this, 'render_vms_event_plan_card'), 10, 1);
    }

    public function event_post_types() {
        return apply_filters('vms_sponsorships_event_post_types', array(
            'event',
            'events',
            'tribe_events',
            'vms_event',
            'vms_event_plan',
        ));
    }

    public function add_event_plan_meta_box() {
        foreach ($this->event_post_types() as $post_type) {
            if (post_type_exists($post_type)) {
                add_meta_box(
                    'vms-sponsorships-event-plan',
                    __('VMS Sponsorships', 'vms-sponsorships'),
                    array($this, 'render_event_plan_meta_box'),
                    $post_type,
                    'side',
                    'default'
                );
            }
        }
    }

    public function render_event_plan_meta_box($post) {
        wp_nonce_field('vms_sponsorships_event_plan_meta', 'vms_sponsorships_event_plan_meta_nonce');

        $tier = get_post_meta($post->ID, '_vms_sponsorship_value_tier', true) ?: 'standard';
        $expected_attendance = get_post_meta($post->ID, '_vms_expected_attendance', true);
        $multiplier = get_post_meta($post->ID, '_vms_sponsorship_price_multiplier', true) ?: '1.0';
        $assignments = $this->repo->get_assignments(array('event_id' => $post->ID, 'limit' => 10));
        $banner_count = $this->repo->count_event_banners($post->ID);
        $banner_cap = absint(get_option('vms_sponsorships_max_event_banners', 1));
        ?>
        <p><strong><?php esc_html_e('Sponsorship value tier', 'vms-sponsorships'); ?></strong></p>
        <p>
            <select name="vms_sponsorship_value_tier" style="width:100%;">
                <?php foreach ($this->value_tiers() as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($tier, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><?php esc_html_e('Expected attendance', 'vms-sponsorships'); ?><br>
                <input type="number" min="0" name="vms_expected_attendance" value="<?php echo esc_attr($expected_attendance); ?>" style="width:100%;">
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Pricing multiplier', 'vms-sponsorships'); ?><br>
                <input type="number" min="0" step="0.01" name="vms_sponsorship_price_multiplier" value="<?php echo esc_attr($multiplier); ?>" style="width:100%;">
            </label>
        </p>
        <hr>
        <p><strong><?php esc_html_e('Physical banner slots', 'vms-sponsorships'); ?></strong><br>
            <?php echo esc_html(sprintf(__('%1$d of %2$d used', 'vms-sponsorships'), $banner_count, $banner_cap)); ?>
        </p>
        <p><strong><?php esc_html_e('Assignments', 'vms-sponsorships'); ?></strong></p>
        <?php if (empty($assignments)) : ?>
            <p><?php esc_html_e('No sponsors assigned yet.', 'vms-sponsorships'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($assignments as $assignment) : ?>
                    <li><?php echo esc_html($assignment->sponsor_display_name . ' — ' . $assignment->slot_key); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-assignments&event_id=' . absint($post->ID))); ?>"><?php esc_html_e('Manage Sponsorships', 'vms-sponsorships'); ?></a></p>
        <p><code>[vms_sponsor_event event_id="<?php echo esc_attr($post->ID); ?>"]</code></p>
        <?php
    }

    public function save_event_plan_meta($post_id, $post) {
        if (!in_array($post->post_type, $this->event_post_types(), true)) {
            return;
        }
        if (!isset($_POST['vms_sponsorships_event_plan_meta_nonce']) || !wp_verify_nonce($_POST['vms_sponsorships_event_plan_meta_nonce'], 'vms_sponsorships_event_plan_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $tier = sanitize_key($_POST['vms_sponsorship_value_tier'] ?? 'standard');
        if (!array_key_exists($tier, $this->value_tiers())) {
            $tier = 'standard';
        }

        update_post_meta($post_id, '_vms_sponsorship_value_tier', $tier);
        update_post_meta($post_id, '_vms_expected_attendance', absint($_POST['vms_expected_attendance'] ?? 0));
        update_post_meta($post_id, '_vms_sponsorship_price_multiplier', max(0, (float) ($_POST['vms_sponsorship_price_multiplier'] ?? 1)));
    }

    public function render_vms_event_plan_card($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $assignments = $this->repo->get_assignments(array('event_id' => $event_id, 'limit' => 20));
        $banner_count = $this->repo->count_event_banners($event_id);
        $banner_cap = absint(get_option('vms_sponsorships_max_event_banners', 1));

        ?>
        <section class="vms-event-plan-card vms-event-plan-card--sponsorships">
            <h3><?php esc_html_e('Sponsorships', 'vms-sponsorships'); ?></h3>
            <p><?php echo esc_html(sprintf(__('Physical banners: %1$d of %2$d used', 'vms-sponsorships'), $banner_count, $banner_cap)); ?></p>
            <?php if (empty($assignments)) : ?>
                <p><?php esc_html_e('No sponsorship assignments yet.', 'vms-sponsorships'); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ($assignments as $assignment) : ?>
                        <li><?php echo esc_html($assignment->sponsor_display_name . ' — ' . $assignment->slot_key . ' — ' . $assignment->status); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
    }

    private function value_tiers() {
        return array(
            'community' => __('Community / Small', 'vms-sponsorships'),
            'standard' => __('Standard', 'vms-sponsorships'),
            'premium' => __('Premium', 'vms-sponsorships'),
            'tentpole' => __('Tentpole', 'vms-sponsorships'),
        );
    }
}
