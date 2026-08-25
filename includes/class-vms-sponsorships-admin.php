<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Admin {
    /** @var VMS_Sponsorships_Repository */
    private $repo;

    /** @var VMS_Sponsorships_Notifications */
    private $notifications;

    public function __construct(VMS_Sponsorships_Repository $repo, VMS_Sponsorships_Notifications $notifications) {
        $this->repo = $repo;
        $this->notifications = $notifications;

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    private function capability() {
        return apply_filters('vms_sponsorships_manage_capability', 'manage_options');
    }

    public function admin_menu() {
        add_menu_page(
            __('VMS Sponsorships', 'vms-sponsorships'),
            __('Sponsorships', 'vms-sponsorships'),
            $this->capability(),
            'vms-sponsorships',
            array($this, 'render_dashboard'),
            'dashicons-megaphone',
            58
        );

        add_submenu_page('vms-sponsorships', __('Dashboard', 'vms-sponsorships'), __('Dashboard', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships', array($this, 'render_dashboard'));
        add_submenu_page('vms-sponsorships', __('Applications', 'vms-sponsorships'), __('Applications', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships-applications', array($this, 'render_applications'));
        add_submenu_page('vms-sponsorships', __('Assignments', 'vms-sponsorships'), __('Assignments', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships-assignments', array($this, 'render_assignments'));
        add_submenu_page('vms-sponsorships', __('Packages', 'vms-sponsorships'), __('Packages', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships-packages', array($this, 'render_packages'));
        add_submenu_page('vms-sponsorships', __('Asset Review', 'vms-sponsorships'), __('Asset Review', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships-assets', array($this, 'render_assets'));
        add_submenu_page('vms-sponsorships', __('Settings', 'vms-sponsorships'), __('Settings', 'vms-sponsorships'), $this->capability(), 'vms-sponsorships-settings', array($this, 'render_settings'));
    }

    public function admin_notices() {
        if (!current_user_can($this->capability())) {
            return;
        }

        if (!empty($_GET['vms_sponsorships_message'])) {
            $message = sanitize_key($_GET['vms_sponsorships_message']);
            $labels = array(
                'saved' => __('Sponsorship changes saved.', 'vms-sponsorships'),
                'status_updated' => __('Status updated.', 'vms-sponsorships'),
                'settings_saved' => __('Sponsorship settings saved.', 'vms-sponsorships'),
                'asset_updated' => __('Asset review status updated.', 'vms-sponsorships'),
            );
            if (isset($labels[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($labels[$message]) . '</p></div>';
            }
        }

        if (!empty($_GET['vms_sponsorships_error'])) {
            $error = sanitize_key($_GET['vms_sponsorships_error']);
            $labels = array(
                'permission' => __('You do not have permission to manage sponsorships.', 'vms-sponsorships'),
                'nonce' => __('Security check failed. Please try again.', 'vms-sponsorships'),
                'missing_data' => __('Required sponsorship data was missing.', 'vms-sponsorships'),
                'banner_cap' => __('This event has reached its physical sponsor banner cap.', 'vms-sponsorships'),
                'save_failed' => __('The sponsorship record could not be saved.', 'vms-sponsorships'),
            );
            $text = $labels[$error] ?? __('Sponsorship action failed.', 'vms-sponsorships');
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
    }

    public function handle_actions() {
        if (empty($_POST['vms_sponsorships_action'])) {
            return;
        }

        if (!current_user_can($this->capability())) {
            $this->redirect_error('permission');
        }

        $action = sanitize_key($_POST['vms_sponsorships_action']);
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'vms_sponsorships_' . $action)) {
            $this->redirect_error('nonce');
        }

        switch ($action) {
            case 'save_package':
                $this->handle_save_package();
                break;
            case 'update_application_status':
                $this->handle_update_application_status();
                break;
            case 'save_assignment':
                $this->handle_save_assignment();
                break;
            case 'save_settings':
                $this->handle_save_settings();
                break;
            case 'update_asset_status':
                $this->handle_update_asset_status();
                break;
            case 'update_fulfillment_item':
                $this->handle_update_fulfillment_item();
                break;
        }
    }

    private function handle_save_package() {
        $id = $this->repo->upsert_package(array(
            'id' => $_POST['id'] ?? 0,
            'name' => $_POST['name'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'scope' => $_POST['scope'] ?? 'event',
            'base_price' => $_POST['base_price'] ?? 0,
            'active' => !empty($_POST['active']),
            'includes_physical_banner' => !empty($_POST['includes_physical_banner']),
            'counts_toward_banner_cap' => !empty($_POST['counts_toward_banner_cap']),
            'requires_approval' => !empty($_POST['requires_approval']),
            'public_display_enabled' => !empty($_POST['public_display_enabled']),
            'email_display_enabled' => !empty($_POST['email_display_enabled']),
            'report_included' => !empty($_POST['report_included']),
            'required_assets' => $_POST['required_assets'] ?? '',
            'fulfillment_template' => $_POST['fulfillment_template'] ?? '',
            'description' => $_POST['description'] ?? '',
        ));

        if (is_wp_error($id)) {
            $this->redirect_error('save_failed');
        }

        $this->redirect_message('saved', 'vms-sponsorships-packages');
    }

    private function handle_update_application_status() {
        $id = absint($_POST['application_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? 'submitted');
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $this->repo->update_application_status($id, $status, $_POST['decline_reason_private'] ?? '');
        $application = $this->repo->get_application($id);

        if ($application) {
            $this->notifications->application_status_changed($application);
        }

        $this->redirect_message('status_updated', 'vms-sponsorships-applications');
    }

    private function handle_save_assignment() {
        $id = absint($_POST['id'] ?? 0);
        $data = array(
            'vendor_id' => $_POST['vendor_id'] ?? null,
            'user_id' => $_POST['user_id'] ?? null,
            'application_id' => $_POST['application_id'] ?? null,
            'event_id' => $_POST['event_id'] ?? null,
            'season_id' => $_POST['season_id'] ?? null,
            'package_id' => $_POST['package_id'] ?? null,
            'slot_key' => $_POST['slot_key'] ?? 'presenting',
            'status' => $_POST['status'] ?? 'prospect',
            'sponsor_display_name' => $_POST['sponsor_display_name'] ?? '',
            'sponsor_tagline' => $_POST['sponsor_tagline'] ?? '',
            'sponsor_url' => $_POST['sponsor_url'] ?? '',
            'amount' => $_POST['amount'] ?? 0,
            'in_kind_value' => $_POST['in_kind_value'] ?? 0,
            'payment_status' => $_POST['payment_status'] ?? 'unpaid',
            'public_display_enabled' => !empty($_POST['public_display_enabled']),
            'placeholder_enabled' => !empty($_POST['placeholder_enabled']),
            'physical_banner_included' => !empty($_POST['physical_banner_included']),
            'banner_slot' => $_POST['banner_slot'] ?? '',
            'asset_status' => $_POST['asset_status'] ?? 'pending_review',
            'fulfillment_status' => $_POST['fulfillment_status'] ?? 'not_started',
            'report_status' => $_POST['report_status'] ?? 'not_ready',
            'internal_notes' => $_POST['internal_notes'] ?? '',
        );

        $result = $id ? $this->repo->update_assignment($id, $data) : $this->repo->create_assignment($data);

        if (is_wp_error($result)) {
            if ('vms_sponsorships_banner_cap_exceeded' === $result->get_error_code()) {
                $this->redirect_error('banner_cap');
            }
            $this->redirect_error('save_failed');
        }

        $this->redirect_message('saved', 'vms-sponsorships-assignments');
    }

    private function handle_save_settings() {
        update_option('vms_sponsorships_max_event_banners', max(0, absint($_POST['max_event_banners'] ?? 1)));
        update_option('vms_sponsorships_placeholder_heading', sanitize_text_field($_POST['placeholder_heading'] ?? ''));
        update_option('vms_sponsorships_placeholder_body', sanitize_textarea_field($_POST['placeholder_body'] ?? ''));
        update_option('vms_sponsorships_placeholder_button', sanitize_text_field($_POST['placeholder_button'] ?? ''));
        update_option('vms_sponsorships_inquiry_page_url', esc_url_raw($_POST['inquiry_page_url'] ?? ''));
        update_option('vms_sponsorships_visibility_title', sanitize_text_field($_POST['visibility_title'] ?? ''));
        update_option('vms_sponsorships_visibility_intro', sanitize_textarea_field($_POST['visibility_intro'] ?? ''));
        update_option('vms_sponsorships_visibility_website', sanitize_text_field($_POST['visibility_website'] ?? ''));
        update_option('vms_sponsorships_visibility_facebook', sanitize_text_field($_POST['visibility_facebook'] ?? ''));
        update_option('vms_sponsorships_visibility_instagram', sanitize_text_field($_POST['visibility_instagram'] ?? ''));
        update_option('vms_sponsorships_visibility_email', sanitize_text_field($_POST['visibility_email'] ?? ''));
        update_option('vms_sponsorships_visibility_attendance', sanitize_text_field($_POST['visibility_attendance'] ?? ''));

        $visibility_last_updated = sanitize_text_field($_POST['visibility_last_updated'] ?? '');
        if ($visibility_last_updated && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visibility_last_updated)) {
            $visibility_last_updated = '';
        }
        update_option('vms_sponsorships_visibility_last_updated', $visibility_last_updated);

        $this->repo->bust_display_cache();
        $this->redirect_message('settings_saved', 'vms-sponsorships-settings');
    }

    private function handle_update_asset_status() {
        $id = absint($_POST['asset_id'] ?? 0);
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $previous_asset = $this->repo->get_asset($id);
        $result = $this->repo->update_asset_status($id, $_POST['status'] ?? 'pending_review', $_POST['rejection_note'] ?? '');
        if (false === $result) {
            $this->redirect_error('save_failed');
        }

        $asset = $this->repo->get_asset($id);
        if ($asset && (!$previous_asset || $previous_asset->status !== $asset->status)) {
            $this->notifications->asset_status_changed($asset);
        }

        $this->redirect_message('asset_updated', 'vms-sponsorships-assets');
    }

    private function handle_update_fulfillment_item() {
        $id = absint($_POST['fulfillment_item_id'] ?? 0);
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $this->repo->update_fulfillment_item($id, $_POST['status'] ?? 'not_started', $_POST['notes'] ?? '');
        $this->redirect_message('saved', 'vms-sponsorships-assignments');
    }

    public function render_dashboard() {
        $applications = $this->repo->get_applications('submitted', 10);
        $assignments = $this->repo->get_assignments(array('limit' => 10));
        $pending_assets = $this->repo->get_assets(array('status' => 'pending_review', 'limit' => 10));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('VMS Sponsorships', 'vms-sponsorships'); ?></h1>
            <p><?php esc_html_e('Manage sponsor applications, packages, event assignments, banner inventory, assets, and fulfillment.', 'vms-sponsorships'); ?></p>

            <div class="vms-sponsorships-admin-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:1100px;">
                <div class="postbox" style="padding:16px;">
                    <h2><?php esc_html_e('New Applications', 'vms-sponsorships'); ?></h2>
                    <p style="font-size:32px;margin:0;"><?php echo esc_html(count($applications)); ?></p>
                    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-applications')); ?>"><?php esc_html_e('Review Applications', 'vms-sponsorships'); ?></a></p>
                </div>
                <div class="postbox" style="padding:16px;">
                    <h2><?php esc_html_e('Pending Assets', 'vms-sponsorships'); ?></h2>
                    <p style="font-size:32px;margin:0;"><?php echo esc_html(count($pending_assets)); ?></p>
                    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-assets')); ?>"><?php esc_html_e('Review Assets', 'vms-sponsorships'); ?></a></p>
                </div>
                <div class="postbox" style="padding:16px;">
                    <h2><?php esc_html_e('Recent Assignments', 'vms-sponsorships'); ?></h2>
                    <p style="font-size:32px;margin:0;"><?php echo esc_html(count($assignments)); ?></p>
                    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-assignments')); ?>"><?php esc_html_e('Manage Assignments', 'vms-sponsorships'); ?></a></p>
                </div>
            </div>

            <h2><?php esc_html_e('First-pass shortcodes', 'vms-sponsorships'); ?></h2>
            <table class="widefat striped" style="max-width:1100px;">
                <tbody>
                    <tr><td><code>[vms_sponsor_event event_id="123"]</code></td><td><?php esc_html_e('Primary event sponsor block.', 'vms-sponsorships'); ?></td></tr>
                    <tr><td><code>[vms_sponsor_slot event_id="123" slot="bar"]</code></td><td><?php esc_html_e('Specific sponsorship slot.', 'vms-sponsorships'); ?></td></tr>
                    <tr><td><code>[vms_sponsor_email event_id="123" slot="presenting"]</code></td><td><?php esc_html_e('Newsletter-safe sponsor block.', 'vms-sponsorships'); ?></td></tr>
                    <tr><td><code>[vms_sponsor_inquiry]</code></td><td><?php esc_html_e('Sponsor landing page with package details and optional inline application form.', 'vms-sponsorships'); ?></td></tr>
                    <tr><td><code>[vms_sponsor_apply event_id="123"]</code></td><td><?php esc_html_e('Public sponsorship application form.', 'vms-sponsorships'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_applications() {
        $applications = $this->repo->get_applications('', 100);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sponsorship Applications', 'vms-sponsorships'); ?></h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Business', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Contact', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Event', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Package / context', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Consideration', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Submitted', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Action', 'vms-sponsorships'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No sponsorship applications yet.', 'vms-sponsorships'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($applications as $application) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($application->business_name); ?></strong><br>
                                <?php if ($application->website_url) : ?><a href="<?php echo esc_url($application->website_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($application->website_url); ?></a><?php endif; ?>
                                <?php if ($application->sponsor_message) : ?><p><?php echo esc_html(wp_trim_words($application->sponsor_message, 24)); ?></p><?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($application->contact_name); ?><br>
                                <a href="mailto:<?php echo esc_attr($application->email); ?>"><?php echo esc_html($application->email); ?></a><br>
                                <?php echo esc_html($application->phone); ?>
                            </td>
                            <td>
                                <?php if ($application->event_id) : ?>
                                    <strong><?php echo esc_html(get_the_title($application->event_id) ?: sprintf(__('Event #%d', 'vms-sponsorships'), $application->event_id)); ?></strong><br>
                                    <span><?php echo esc_html(sprintf(__('ID %d', 'vms-sponsorships'), $application->event_id)); ?></span>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($application->requested_package_id) : ?>
                                    <strong><?php echo esc_html($this->package_name($application->requested_package_id)); ?></strong><br>
                                <?php endif; ?>
                                <span><?php echo esc_html($this->scope_label($application->scope ?? 'event')); ?></span><br>
                                <span><?php echo esc_html($this->slot_label($application->requested_slot_key ?? '')); ?></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($this->consideration_type_label($application->consideration_type ?? 'not_sure')); ?></strong>
                                <?php if (null !== $application->estimated_trade_value && '' !== (string) $application->estimated_trade_value) : ?>
                                    <br><span><?php echo esc_html(sprintf(__('Estimated trade value: %s', 'vms-sponsorships'), '$' . number_format_i18n((float) $application->estimated_trade_value, 2))); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($application->trade_offer_details)) : ?>
                                    <p><?php echo esc_html(wp_trim_words($application->trade_offer_details, 28)); ?></p>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($application->status); ?></code></td>
                            <td><?php echo esc_html($application->submitted_at); ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('vms_sponsorships_update_application_status'); ?>
                                    <input type="hidden" name="vms_sponsorships_action" value="update_application_status">
                                    <input type="hidden" name="application_id" value="<?php echo esc_attr($application->id); ?>">
                                    <select name="status">
                                        <?php foreach ($this->repo->application_statuses() as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($application->status, $status); ?>><?php echo esc_html($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="decline_reason_private" placeholder="<?php esc_attr_e('Private decline/review note', 'vms-sponsorships'); ?>" rows="2" style="width:100%;margin-top:6px;"><?php echo esc_textarea($application->decline_reason_private); ?></textarea>
                                    <button class="button button-primary" type="submit"><?php esc_html_e('Update', 'vms-sponsorships'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_assignments() {
        $assignments = $this->repo->get_assignments(array('limit' => 100));
        $packages = $this->repo->get_packages(true);
        $edit = !empty($_GET['assignment_id']) ? $this->repo->get_assignment(absint($_GET['assignment_id'])) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sponsorship Assignments', 'vms-sponsorships'); ?></h1>

            <h2><?php echo $edit ? esc_html__('Edit Assignment', 'vms-sponsorships') : esc_html__('Create Assignment', 'vms-sponsorships'); ?></h2>
            <form method="post" style="max-width:900px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
                <?php wp_nonce_field('vms_sponsorships_save_assignment'); ?>
                <input type="hidden" name="vms_sponsorships_action" value="save_assignment">
                <input type="hidden" name="id" value="<?php echo esc_attr($edit ? $edit->id : 0); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="sponsor_display_name"><?php esc_html_e('Sponsor display name', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_display_name" name="sponsor_display_name" value="<?php echo esc_attr($edit ? $edit->sponsor_display_name : ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="event_id"><?php esc_html_e('Event ID', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" id="event_id" name="event_id" value="<?php echo esc_attr($edit ? $edit->event_id : ''); ?>"> <p class="description"><?php esc_html_e('Link this assignment to an Event Plan/Event post ID.', 'vms-sponsorships'); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="package_id"><?php esc_html_e('Package', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="package_id" name="package_id">
                                <option value=""><?php esc_html_e('No package', 'vms-sponsorships'); ?></option>
                                <?php foreach ($packages as $package) : ?>
                                    <option value="<?php echo esc_attr($package->id); ?>" <?php selected($edit ? $edit->package_id : '', $package->id); ?>><?php echo esc_html($package->name . ' — $' . number_format_i18n((float) $package->base_price, 2)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="slot_key"><?php esc_html_e('Slot', 'vms-sponsorships'); ?></label></th>
                        <td><input id="slot_key" name="slot_key" value="<?php echo esc_attr($edit ? $edit->slot_key : 'presenting'); ?>"> <p class="description">presenting, bar, veterans, kids, food_truck, supporting</p></td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php esc_html_e('Status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <?php foreach ($this->repo->assignment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($edit ? $edit->status : 'prospect', $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="payment_status"><?php esc_html_e('Payment status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="payment_status" name="payment_status">
                                <?php foreach ($this->repo->payment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($edit ? $edit->payment_status : 'unpaid', $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amount"><?php esc_html_e('Amount', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" step="0.01" id="amount" name="amount" value="<?php echo esc_attr($edit ? $edit->amount : '0.00'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="in_kind_value"><?php esc_html_e('In-kind value', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" step="0.01" id="in_kind_value" name="in_kind_value" value="<?php echo esc_attr($edit ? $edit->in_kind_value : '0.00'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="sponsor_url"><?php esc_html_e('Sponsor URL', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_url" name="sponsor_url" value="<?php echo esc_attr($edit ? $edit->sponsor_url : ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="sponsor_tagline"><?php esc_html_e('Tagline', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_tagline" name="sponsor_tagline" value="<?php echo esc_attr($edit ? $edit->sponsor_tagline : ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Display controls', 'vms-sponsorships'); ?></th>
                        <td>
                            <label><input type="checkbox" name="public_display_enabled" value="1" <?php checked($edit ? $edit->public_display_enabled : 1, 1); ?>> <?php esc_html_e('Public display enabled', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="placeholder_enabled" value="1" <?php checked($edit ? $edit->placeholder_enabled : 1, 1); ?>> <?php esc_html_e('Placeholder enabled if slot is open', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="physical_banner_included" value="1" <?php checked($edit ? $edit->physical_banner_included : 0, 1); ?>> <?php esc_html_e('Includes physical on-site banner', 'vms-sponsorships'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="asset_status"><?php esc_html_e('Asset status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="asset_status" name="asset_status">
                                <?php foreach ($this->repo->asset_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($edit ? $edit->asset_status : 'pending_review', $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fulfillment_status"><?php esc_html_e('Fulfillment status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="fulfillment_status" name="fulfillment_status">
                                <?php foreach ($this->repo->fulfillment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($edit ? $edit->fulfillment_status : 'not_started', $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="internal_notes"><?php esc_html_e('Internal notes', 'vms-sponsorships'); ?></label></th>
                        <td><textarea class="large-text" rows="4" id="internal_notes" name="internal_notes"><?php echo esc_textarea($edit ? $edit->internal_notes : ''); ?></textarea></td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Assignment', 'vms-sponsorships'); ?></button></p>
            </form>

            <?php if ($edit) : ?>
                <h2><?php esc_html_e('Fulfillment Checklist', 'vms-sponsorships'); ?></h2>
                <?php $items = $this->repo->get_fulfillment_items($edit->id); ?>
                <table class="widefat striped" style="max-width:900px;">
                    <thead><tr><th><?php esc_html_e('Item', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Notes', 'vms-sponsorships'); ?></th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($items)) : ?><tr><td colspan="4"><?php esc_html_e('No fulfillment items generated yet.', 'vms-sponsorships'); ?></td></tr><?php endif; ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item->label); ?></td>
                                <td>
                                    <form method="post">
                                        <?php wp_nonce_field('vms_sponsorships_update_fulfillment_item'); ?>
                                        <input type="hidden" name="vms_sponsorships_action" value="update_fulfillment_item">
                                        <input type="hidden" name="fulfillment_item_id" value="<?php echo esc_attr($item->id); ?>">
                                        <select name="status">
                                            <?php foreach ($this->repo->fulfillment_statuses() as $status) : ?>
                                                <option value="<?php echo esc_attr($status); ?>" <?php selected($item->status, $status); ?>><?php echo esc_html($status); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td><input name="notes" value="<?php echo esc_attr($item->notes); ?>"></td>
                                <td><button class="button" type="submit"><?php esc_html_e('Save', 'vms-sponsorships'); ?></button></form></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2><?php esc_html_e('Existing Assignments', 'vms-sponsorships'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Sponsor', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Event', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Slot', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Payment', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Banner', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Amount', 'vms-sponsorships'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'vms-sponsorships'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)) : ?><tr><td colspan="9"><?php esc_html_e('No sponsorship assignments yet.', 'vms-sponsorships'); ?></td></tr><?php endif; ?>
                    <?php foreach ($assignments as $assignment) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($assignment->sponsor_display_name); ?></strong></td>
                            <td><?php echo $assignment->event_id ? esc_html($assignment->event_id) : '&mdash;'; ?></td>
                            <td><code><?php echo esc_html($assignment->slot_key); ?></code></td>
                            <td><code><?php echo esc_html($assignment->status); ?></code></td>
                            <td><code><?php echo esc_html($assignment->payment_status); ?></code></td>
                            <td><?php echo $assignment->physical_banner_included ? esc_html__('Yes', 'vms-sponsorships') : esc_html__('No', 'vms-sponsorships'); ?></td>
                            <td><?php echo esc_html('$' . number_format_i18n((float) $assignment->amount, 2)); ?></td>
                            <td><code>[vms_sponsor_slot event_id="<?php echo esc_attr($assignment->event_id); ?>" slot="<?php echo esc_attr($assignment->slot_key); ?>"]</code></td>
                            <td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-assignments&assignment_id=' . absint($assignment->id))); ?>"><?php esc_html_e('Edit', 'vms-sponsorships'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_packages() {
        $packages = $this->repo->get_packages(false);
        $edit = !empty($_GET['package_id']) ? $this->repo->get_package(absint($_GET['package_id'])) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sponsorship Packages', 'vms-sponsorships'); ?></h1>

            <h2><?php echo $edit ? esc_html__('Edit Package', 'vms-sponsorships') : esc_html__('Create Package', 'vms-sponsorships'); ?></h2>
            <form method="post" style="max-width:900px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
                <?php wp_nonce_field('vms_sponsorships_save_package'); ?>
                <input type="hidden" name="vms_sponsorships_action" value="save_package">
                <input type="hidden" name="id" value="<?php echo esc_attr($edit ? $edit->id : 0); ?>">
                <table class="form-table" role="presentation">
                    <tr><th><label for="name"><?php esc_html_e('Name', 'vms-sponsorships'); ?></label></th><td><input class="regular-text" id="name" name="name" value="<?php echo esc_attr($edit ? $edit->name : ''); ?>" required></td></tr>
                    <tr><th><label for="slug"><?php esc_html_e('Slug', 'vms-sponsorships'); ?></label></th><td><input class="regular-text" id="slug" name="slug" value="<?php echo esc_attr($edit ? $edit->slug : ''); ?>"></td></tr>
                    <tr>
                        <th><label for="scope"><?php esc_html_e('Scope', 'vms-sponsorships'); ?></label></th>
                        <td><select id="scope" name="scope"><?php foreach ($this->repo->scopes() as $scope) : ?><option value="<?php echo esc_attr($scope); ?>" <?php selected($edit ? $edit->scope : 'event', $scope); ?>><?php echo esc_html($scope); ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr><th><label for="base_price"><?php esc_html_e('Base price', 'vms-sponsorships'); ?></label></th><td><input type="number" step="0.01" id="base_price" name="base_price" value="<?php echo esc_attr($edit ? $edit->base_price : '0.00'); ?>"></td></tr>
                    <tr>
                        <th><?php esc_html_e('Package rules', 'vms-sponsorships'); ?></th>
                        <td>
                            <label><input type="checkbox" name="active" value="1" <?php checked($edit ? $edit->active : 1, 1); ?>> <?php esc_html_e('Active', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="requires_approval" value="1" <?php checked($edit ? $edit->requires_approval : 1, 1); ?>> <?php esc_html_e('Requires approval', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="public_display_enabled" value="1" <?php checked($edit ? $edit->public_display_enabled : 1, 1); ?>> <?php esc_html_e('Public display enabled', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="email_display_enabled" value="1" <?php checked($edit ? $edit->email_display_enabled : 1, 1); ?>> <?php esc_html_e('Email/newsletter display enabled', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="report_included" value="1" <?php checked($edit ? $edit->report_included : 1, 1); ?>> <?php esc_html_e('Sponsor report included', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="includes_physical_banner" value="1" <?php checked($edit ? $edit->includes_physical_banner : 0, 1); ?>> <?php esc_html_e('Includes physical banner/signage', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="counts_toward_banner_cap" value="1" <?php checked($edit ? $edit->counts_toward_banner_cap : 0, 1); ?>> <?php esc_html_e('Counts toward event banner cap', 'vms-sponsorships'); ?></label>
                        </td>
                    </tr>
                    <tr><th><label for="required_assets"><?php esc_html_e('Required assets', 'vms-sponsorships'); ?></label></th><td><textarea class="large-text code" rows="3" id="required_assets" name="required_assets"><?php echo esc_textarea($edit ? $edit->required_assets : '["logo"]'); ?></textarea><p class="description"><?php esc_html_e('JSON list, for example: ["logo", "web_banner"]', 'vms-sponsorships'); ?></p></td></tr>
                    <tr><th><label for="fulfillment_template"><?php esc_html_e('Fulfillment template', 'vms-sponsorships'); ?></label></th><td><textarea class="large-text code" rows="6" id="fulfillment_template" name="fulfillment_template"><?php echo esc_textarea($edit ? $edit->fulfillment_template : '[{"key":"event_page_logo","label":"Logo added to event page"},{"key":"report_delivered","label":"Sponsor report delivered"}]'); ?></textarea></td></tr>
                    <tr><th><label for="description"><?php esc_html_e('Description', 'vms-sponsorships'); ?></label></th><td><textarea class="large-text" rows="4" id="description" name="description"><?php echo esc_textarea($edit ? $edit->description : ''); ?></textarea></td></tr>
                </table>
                <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Package', 'vms-sponsorships'); ?></button></p>
            </form>

            <h2><?php esc_html_e('Existing Packages', 'vms-sponsorships'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Name', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Scope', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Base Price', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Banner', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Active', 'vms-sponsorships'); ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($packages as $package) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($package->name); ?></strong><br><code><?php echo esc_html($package->slug); ?></code></td>
                            <td><?php echo esc_html($package->scope); ?></td>
                            <td><?php echo esc_html('$' . number_format_i18n((float) $package->base_price, 2)); ?></td>
                            <td><?php echo $package->includes_physical_banner ? esc_html__('Yes', 'vms-sponsorships') : esc_html__('No', 'vms-sponsorships'); ?></td>
                            <td><?php echo $package->active ? esc_html__('Yes', 'vms-sponsorships') : esc_html__('No', 'vms-sponsorships'); ?></td>
                            <td><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vms-sponsorships-packages&package_id=' . absint($package->id))); ?>"><?php esc_html_e('Edit', 'vms-sponsorships'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_assets() {
        $assets = $this->repo->get_assets(array('limit' => 100));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sponsor Asset Review', 'vms-sponsorships'); ?></h1>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Preview', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Type', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Assignment', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Uploaded', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Action', 'vms-sponsorships'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($assets)) : ?><tr><td colspan="6"><?php esc_html_e('No sponsor assets uploaded yet.', 'vms-sponsorships'); ?></td></tr><?php endif; ?>
                    <?php foreach ($assets as $asset) : ?>
                        <tr>
                            <td><?php echo wp_get_attachment_image((int) $asset->attachment_id, array(120, 80)); ?></td>
                            <td><code><?php echo esc_html($asset->asset_type); ?></code></td>
                            <td><?php echo $asset->assignment_id ? esc_html($asset->assignment_id) : '&mdash;'; ?></td>
                            <td><code><?php echo esc_html($asset->status); ?></code></td>
                            <td><?php echo esc_html($asset->uploaded_at); ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('vms_sponsorships_update_asset_status'); ?>
                                    <input type="hidden" name="vms_sponsorships_action" value="update_asset_status">
                                    <input type="hidden" name="asset_id" value="<?php echo esc_attr($asset->id); ?>">
                                    <select name="status">
                                        <?php foreach ($this->repo->asset_statuses() as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($asset->status, $status); ?>><?php echo esc_html($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input name="rejection_note" value="<?php echo esc_attr($asset->rejection_note); ?>" placeholder="<?php esc_attr_e('Private/public revision note', 'vms-sponsorships'); ?>">
                                    <button class="button" type="submit"><?php esc_html_e('Save', 'vms-sponsorships'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_settings() {
        $visibility_defaults = VMS_Sponsorships_Install::default_visibility_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sponsorship Settings', 'vms-sponsorships'); ?></h1>
            <form method="post" style="max-width:900px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
                <?php wp_nonce_field('vms_sponsorships_save_settings'); ?>
                <input type="hidden" name="vms_sponsorships_action" value="save_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="max_event_banners"><?php esc_html_e('Max physical sponsor banners per event', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" min="0" max="10" id="max_event_banners" name="max_event_banners" value="<?php echo esc_attr(get_option('vms_sponsorships_max_event_banners', 1)); ?>"><p class="description"><?php esc_html_e('Default recommendation: 1. This keeps on-site sponsor signage exclusive and tasteful.', 'vms-sponsorships'); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="placeholder_heading"><?php esc_html_e('Placeholder heading', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="placeholder_heading" name="placeholder_heading" value="<?php echo esc_attr(get_option('vms_sponsorships_placeholder_heading', 'Your business could sponsor this show')); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="placeholder_body"><?php esc_html_e('Placeholder body', 'vms-sponsorships'); ?></label></th>
                        <td><textarea class="large-text" rows="3" id="placeholder_body" name="placeholder_body"><?php echo esc_textarea(get_option('vms_sponsorships_placeholder_body', 'Put your brand in front of East Texas music fans before, during, and after the event.')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="placeholder_button"><?php esc_html_e('Placeholder button', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="placeholder_button" name="placeholder_button" value="<?php echo esc_attr(get_option('vms_sponsorships_placeholder_button', 'Sponsor this event')); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="inquiry_page_url"><?php esc_html_e('Sponsorship inquiry page URL', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="inquiry_page_url" name="inquiry_page_url" value="<?php echo esc_attr(get_option('vms_sponsorships_inquiry_page_url', '')); ?>"><p class="description"><?php esc_html_e('Used by placeholder CTA buttons. Best practice is a dedicated page containing [vms_sponsor_inquiry]. Leave blank to use the built-in application shortcode page/form.', 'vms-sponsorships'); ?></p></td>
                    </tr>
                </table>
                <h2><?php esc_html_e('Sponsor Visibility Section', 'vms-sponsorships'); ?></h2>
                <p><?php esc_html_e('These values appear above the public sponsorship application as short proof points. Keep them concise and update them manually as needed.', 'vms-sponsorships'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="visibility_title"><?php esc_html_e('Section title', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_title" name="visibility_title" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_title', $visibility_defaults['vms_sponsorships_visibility_title'])); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_intro"><?php esc_html_e('Short intro', 'vms-sponsorships'); ?></label></th>
                        <td><textarea class="large-text" rows="3" id="visibility_intro" name="visibility_intro"><?php echo esc_textarea(get_option('vms_sponsorships_visibility_intro', $visibility_defaults['vms_sponsorships_visibility_intro'])); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_website"><?php esc_html_e('Website / event page visibility', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_website" name="visibility_website" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_website', $visibility_defaults['vms_sponsorships_visibility_website'])); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_facebook"><?php esc_html_e('Facebook followers', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_facebook" name="visibility_facebook" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_facebook', $visibility_defaults['vms_sponsorships_visibility_facebook'])); ?>" placeholder="<?php esc_attr_e('Example: 4,800 followers', 'vms-sponsorships'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_instagram"><?php esc_html_e('Instagram followers', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_instagram" name="visibility_instagram" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_instagram', $visibility_defaults['vms_sponsorships_visibility_instagram'])); ?>" placeholder="<?php esc_attr_e('Example: 3,200 followers', 'vms-sponsorships'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_email"><?php esc_html_e('Ranger Report / email subscribers', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_email" name="visibility_email" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_email', $visibility_defaults['vms_sponsorships_visibility_email'])); ?>" placeholder="<?php esc_attr_e('Example: 1,900 subscribers', 'vms-sponsorships'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_attendance"><?php esc_html_e('Typical attendance / audience note', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="visibility_attendance" name="visibility_attendance" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_attendance', $visibility_defaults['vms_sponsorships_visibility_attendance'])); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="visibility_last_updated"><?php esc_html_e('Last updated date', 'vms-sponsorships'); ?></label></th>
                        <td><input type="date" id="visibility_last_updated" name="visibility_last_updated" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_last_updated', $visibility_defaults['vms_sponsorships_visibility_last_updated'])); ?>"></td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Settings', 'vms-sponsorships'); ?></button></p>
            </form>
        </div>
        <?php
    }

    private function package_name($package_id) {
        $package = $this->repo->get_package((int) $package_id);
        if (!$package) {
            return sprintf(__('Package #%d', 'vms-sponsorships'), (int) $package_id);
        }

        return $package->name;
    }

    private function scope_label($scope) {
        $labels = array(
            'event' => __('Event sponsorship', 'vms-sponsorships'),
            'feature' => __('Feature sponsorship', 'vms-sponsorships'),
            'season' => __('Season sponsorship', 'vms-sponsorships'),
            'venue' => __('Venue sponsorship', 'vms-sponsorships'),
            'newsletter' => __('Newsletter sponsorship', 'vms-sponsorships'),
        );

        $scope = sanitize_key($scope);

        return $labels[$scope] ?? ucwords(str_replace('_', ' ', $scope ?: 'event'));
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

        $slot = sanitize_key($slot);
        if (!$slot) {
            return __('Slot not specified', 'vms-sponsorships');
        }

        return $labels[$slot] ?? ucwords(str_replace('_', ' ', $slot));
    }

    private function consideration_type_label($type) {
        $labels = array(
            'cash' => __('Cash sponsorship', 'vms-sponsorships'),
            'trade_in_kind' => __('Trade / in-kind sponsorship', 'vms-sponsorships'),
            'cash_and_trade' => __('Cash + trade combination', 'vms-sponsorships'),
            'not_sure' => __('Not sure yet', 'vms-sponsorships'),
        );

        $type = sanitize_key($type);

        return $labels[$type] ?? $labels['not_sure'];
    }

    private function redirect_message($message, $page) {
        wp_safe_redirect(add_query_arg(array(
            'page' => $page,
            'vms_sponsorships_message' => sanitize_key($message),
        ), admin_url('admin.php')));
        exit;
    }

    private function redirect_error($error) {
        $page = !empty($_GET['page']) ? sanitize_key($_GET['page']) : 'vms-sponsorships';
        wp_safe_redirect(add_query_arg(array(
            'page' => $page,
            'vms_sponsorships_error' => sanitize_key($error),
        ), admin_url('admin.php')));
        exit;
    }
}
