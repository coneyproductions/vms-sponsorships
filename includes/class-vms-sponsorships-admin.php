<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Admin {
    private const ROOT_SLUG = 'vms-sponsorships';
    private const MENU_ICON = 'dashicons-megaphone';
    private const MENU_POSITION = 58;

    /** @var VMS_Sponsorships_Repository */
    private $repo;

    /** @var VMS_Sponsorships_Notifications */
    private $notifications;

    /** @var bool */
    private $canonical_registry_registered = false;

    public function __construct(VMS_Sponsorships_Repository $repo, VMS_Sponsorships_Notifications $notifications) {
        $this->repo = $repo;
        $this->notifications = $notifications;

        add_action('vms_admin_register_pages', array($this, 'register_vms_admin_pages'), 30);
        add_action('admin_menu', array($this, 'register_admin_menu_fallback'), 99);
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    private function capability() {
        return apply_filters('vms_sponsorships_manage_capability', 'manage_options');
    }

    private function admin_pages() {
        return array(
            self::ROOT_SLUG => array(
                'slug' => self::ROOT_SLUG,
                'page_title' => __('VMS Sponsorships', 'vms-sponsorships'),
                'menu_title' => __('Sponsorships', 'vms-sponsorships'),
                'submenu_title' => __('Dashboard', 'vms-sponsorships'),
                'callback' => array($this, 'render_dashboard'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 35,
                'description' => __('Operator dashboard for sponsorship pipeline health, asset review, assignments, and sponsor visibility stats.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-applications' => array(
                'slug' => 'vms-sponsorships-applications',
                'page_title' => __('Sponsorship Applications', 'vms-sponsorships'),
                'menu_title' => __('Applications', 'vms-sponsorships'),
                'callback' => array($this, 'render_applications'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 36,
                'description' => __('Review incoming sponsor applications and update sponsorship statuses.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-assignments' => array(
                'slug' => 'vms-sponsorships-assignments',
                'page_title' => __('Sponsorship Assignments', 'vms-sponsorships'),
                'menu_title' => __('Assignments', 'vms-sponsorships'),
                'callback' => array($this, 'render_assignments'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 37,
                'description' => __('Manage sponsor assignments, fulfillment progress, and package delivery.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-packages' => array(
                'slug' => 'vms-sponsorships-packages',
                'page_title' => __('Sponsorship Packages', 'vms-sponsorships'),
                'menu_title' => __('Packages', 'vms-sponsorships'),
                'callback' => array($this, 'render_packages'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 38,
                'description' => __('Configure sponsorship packages, pricing, fulfillment templates, and banner rules.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-assets' => array(
                'slug' => 'vms-sponsorships-assets',
                'page_title' => __('Sponsor Asset Review', 'vms-sponsorships'),
                'menu_title' => __('Asset Review', 'vms-sponsorships'),
                'callback' => array($this, 'render_assets'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 39,
                'description' => __('Approve logos and sponsor creative before sponsorship placements go live.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-visibility' => array(
                'slug' => 'vms-sponsorships-visibility',
                'page_title' => __('Visibility Stats', 'vms-sponsorships'),
                'menu_title' => __('Visibility Stats', 'vms-sponsorships'),
                'callback' => array($this, 'render_visibility_stats'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 40,
                'description' => __('Maintain sponsor-facing audience and visibility proof points with freshness tracking.', 'vms-sponsorships'),
            ),
            'vms-sponsorships-settings' => array(
                'slug' => 'vms-sponsorships-settings',
                'page_title' => __('Sponsorship Settings', 'vms-sponsorships'),
                'menu_title' => __('Settings', 'vms-sponsorships'),
                'callback' => array($this, 'render_settings'),
                'category' => 'marketing_social',
                'section' => 'marketing_sales',
                'order' => 41,
                'description' => __('Update banner limits, inquiry defaults, and public sponsorship workflow settings.', 'vms-sponsorships'),
            ),
        );
    }

    public function register_vms_admin_pages() {
        $register_admin_page = vms_sponsorships_core_function('vms_register_admin_page');
        if ($register_admin_page === '') {
            return;
        }

        foreach ($this->admin_pages() as $page) {
            $registered = $register_admin_page(array(
                'id' => $page['slug'],
                'slug' => $page['slug'],
                'page_title' => $page['page_title'],
                'menu_title' => $page['menu_title'],
                'category' => $page['category'],
                'capability' => $this->capability(),
                'callback' => $page['callback'],
                'section' => $page['section'],
                'order' => $page['order'],
                'source' => 'vms-sponsorships',
                'description' => $page['description'],
                'top_nav' => $page['slug'] === self::ROOT_SLUG,
                'register' => true,
            ));
            if ($registered !== false) {
                $this->canonical_registry_registered = true;
            }
        }
    }

    public function register_admin_menu_fallback() {
        $pages = $this->admin_pages();
        $root_page = $pages[self::ROOT_SLUG];

        if ($this->has_vms_parent_menu()) {
            if ($this->canonical_registry_registered) {
                return;
            }
            if (
                (function_exists('vms_admin_registry_is_available') && vms_admin_registry_is_available())
                || vms_sponsorships_core_function('vms_register_admin_page') !== ''
            ) {
                return;
            }

            $parent_slug = $this->get_vms_parent_slug();
            foreach ($pages as $page) {
                add_submenu_page(
                    $parent_slug,
                    $page['page_title'],
                    $page['menu_title'],
                    $this->capability(),
                    $page['slug'],
                    $page['callback']
                );
            }

            return;
        }

        add_menu_page(
            $root_page['page_title'],
            $root_page['menu_title'],
            $this->capability(),
            self::ROOT_SLUG,
            $root_page['callback'],
            self::MENU_ICON,
            self::MENU_POSITION
        );

        add_submenu_page(
            self::ROOT_SLUG,
            $root_page['page_title'],
            $root_page['submenu_title'],
            $this->capability(),
            self::ROOT_SLUG,
            $root_page['callback']
        );

        foreach ($pages as $slug => $page) {
            if ($slug === self::ROOT_SLUG) {
                continue;
            }

            add_submenu_page(
                self::ROOT_SLUG,
                $page['page_title'],
                $page['menu_title'],
                $this->capability(),
                $page['slug'],
                $page['callback']
            );
        }
    }

    public function admin_notices() {
        if (!current_user_can($this->capability())) {
            return;
        }

        if (!empty($_GET['vms_sponsorships_message'])) {
            $message = sanitize_key(wp_unslash($_GET['vms_sponsorships_message']));
            $labels = array(
                'saved' => __('Sponsorship changes saved.', 'vms-sponsorships'),
                'assignment_saved' => __('Assignment saved.', 'vms-sponsorships'),
                'assignment_created' => __('Assignment created.', 'vms-sponsorships'),
                'assignment_created_approved' => __('Assignment created and the application was approved.', 'vms-sponsorships'),
                'assignment_exists' => __('An assignment already exists for this application. Duplicate creation was blocked.', 'vms-sponsorships'),
                'status_updated' => __('Status updated.', 'vms-sponsorships'),
                'settings_saved' => __('Sponsorship settings saved.', 'vms-sponsorships'),
                'visibility_saved' => __('Visibility stats saved.', 'vms-sponsorships'),
                'asset_updated' => __('Asset review status updated.', 'vms-sponsorships'),
            );
            if (isset($labels[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($labels[$message]) . '</p></div>';
            }
        }

        if (!empty($_GET['vms_sponsorships_error'])) {
            $error = sanitize_key(wp_unslash($_GET['vms_sponsorships_error']));
            $labels = array(
                'permission' => __('You do not have permission to manage sponsorships.', 'vms-sponsorships'),
                'nonce' => __('Security check failed. Please try again.', 'vms-sponsorships'),
                'missing_data' => __('Required sponsorship data was missing.', 'vms-sponsorships'),
                'application_missing' => __('The selected sponsorship application could not be found.', 'vms-sponsorships'),
                'application_approval_failed' => __('The assignment was created, but the application could not be marked approved.', 'vms-sponsorships'),
                'assignment_scope_dates_missing' => __('Season/date-range assignments need both a start date and an end date.', 'vms-sponsorships'),
                'assignment_scope_dates_invalid' => __('The end date must be on or after the start date for a season/date-range assignment.', 'vms-sponsorships'),
                'assignment_scope_conflict' => __('This slot already has overlapping event or seasonal coverage for the same event date. Remove the overlap before saving.', 'vms-sponsorships'),
                'primary_scope_conflict' => __('This presenting or primary slot already has overlapping event or seasonal coverage for the same event date. Remove the overlap before saving.', 'vms-sponsorships'),
                'banner_cap' => __('This event has reached its physical sponsor banner cap.', 'vms-sponsorships'),
                'save_failed' => __('The sponsorship record could not be saved.', 'vms-sponsorships'),
            );
            $text = $labels[$error] ?? __('Sponsorship action failed.', 'vms-sponsorships');
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
    }

    private function get_vms_parent_slug() {
        if (function_exists('vms_admin_registry_parent_slug')) {
            return (string) vms_admin_registry_parent_slug();
        }

        $parent_slug = vms_sponsorships_core_function('vms_admin_menu_parent_slug');
        if ($parent_slug !== '') {
            return (string) $parent_slug();
        }

        return 'vms-dashboard';
    }

    private function has_vms_parent_menu() {
        if (function_exists('vms_admin_registry_has_parent_menu')) {
            return vms_admin_registry_has_parent_menu();
        }

        global $menu;

        if (!is_array($menu)) {
            return false;
        }

        $parent_slug = $this->get_vms_parent_slug();

        foreach ($menu as $item) {
            if (is_array($item) && isset($item[2]) && (string) $item[2] === $parent_slug) {
                return true;
            }
        }

        return false;
    }

    private function page_url($slug, $args = array()) {
        $page_url = vms_sponsorships_core_function('vms_admin_ui_page_url');
        if ($page_url !== '') {
            return $page_url($slug, $args);
        }

        $url = admin_url('admin.php?page=' . rawurlencode($slug));
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    private function application_anchor_id($application_id) {
        return 'vms-sponsorships-application-' . absint($application_id);
    }

    private function highlighted_application_id() {
        return !empty($_GET['highlight_application']) ? absint(wp_unslash($_GET['highlight_application'])) : 0;
    }

    private function application_review_url($application_id) {
        $application_id = absint($application_id);
        $url = $this->page_url('vms-sponsorships-applications');

        if ($application_id <= 0) {
            return $url;
        }

        return add_query_arg(array(
            'highlight_application' => $application_id,
        ), $url);
    }

    private function assignment_edit_url($assignment_id, $args = array()) {
        $assignment_id = absint($assignment_id);
        $args = array_merge(array(
            'assignment_id' => $assignment_id,
        ), $args);

        return $this->page_url('vms-sponsorships-assignments', $args);
    }

    private function assignment_create_from_application_url($application_id, $approve_after_create = false) {
        $args = array(
            'application_id' => absint($application_id),
        );

        if ($approve_after_create) {
            $args['approve_application'] = 1;
        }

        return $this->page_url('vms-sponsorships-assignments', $args);
    }

    private function asset_anchor_id($asset_id) {
        return 'vms-sponsorships-asset-' . absint($asset_id);
    }

    private function asset_review_url($asset_id) {
        $asset_id = absint($asset_id);
        $url = $this->page_url('vms-sponsorships-assets');

        if ($asset_id <= 0) {
            return $url;
        }

        return $url . '#' . $this->asset_anchor_id($asset_id);
    }

    private function render_page_header($title, $subtitle = '') {
        $should_render_fallback_nav = true;
        if (function_exists('vms_admin_registry_should_render_fallback_nav')) {
            $should_render_fallback_nav = vms_admin_registry_should_render_fallback_nav();
        } else {
            $is_vms_screen = vms_sponsorships_core_function('vms_admin_ui_is_vms_screen');
            if ($is_vms_screen !== '' && $is_vms_screen()) {
                $should_render_fallback_nav = false;
            }
        }

        $render_top_nav = vms_sponsorships_core_function('vms_admin_ui_render_top_nav');
        if ($should_render_fallback_nav && $render_top_nav !== '') {
            echo '<section class="vms-sponsorships-admin-top-nav">';
            $render_top_nav();
            echo '</section>';
        }

        echo '<div class="vms-sponsorships-admin-header">';
        echo '<h1>' . esc_html($title) . '</h1>';

        if ($subtitle !== '') {
            echo '<p class="description">' . esc_html($subtitle) . '</p>';
        }

        echo '</div>';

        $this->render_local_nav();
    }

    private function render_local_nav() {
        $current_slug = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $pages = $this->admin_pages();

        if ($current_slug === '' || !isset($pages[$current_slug])) {
            return;
        }

        static $rendered_styles = false;
        if (!$rendered_styles) {
            $rendered_styles = true;
            ?>
            <style>
                .vms-sponsorships-local-nav {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin: 16px 0 20px;
                }
                .vms-sponsorships-local-nav__link {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 36px;
                    padding: 0 14px;
                    border-radius: 999px;
                    border: 1px solid #d0d7de;
                    background: #fff;
                    color: #1f2937;
                    text-decoration: none;
                    font-weight: 600;
                }
                .vms-sponsorships-local-nav__link:hover {
                    background: #f8fafc;
                    color: #111827;
                }
                .vms-sponsorships-local-nav__link[aria-current="page"] {
                    border-color: #1d4ed8;
                    background: #dbeafe;
                    color: #1d4ed8;
                }
            </style>
            <?php
        }

        echo '<nav class="vms-sponsorships-local-nav" aria-label="' . esc_attr__('Sponsorship pages', 'vms-sponsorships') . '">';
        foreach ($pages as $slug => $page) {
            $label = ($slug === self::ROOT_SLUG)
                ? __('Sponsorships', 'vms-sponsorships')
                : $page['menu_title'];

            echo '<a class="vms-sponsorships-local-nav__link" href="' . esc_url($this->page_url($slug)) . '"';
            if ($slug === $current_slug) {
                echo ' aria-current="page"';
            }
            echo '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    public function handle_actions() {
        $request = wp_unslash($_POST);
        if (empty($request['vms_sponsorships_action'])) {
            return;
        }

        if (!current_user_can($this->capability())) {
            $this->redirect_error('permission');
        }

        $action = sanitize_key($request['vms_sponsorships_action']);
        $nonce = sanitize_text_field($request['_wpnonce'] ?? '');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_sponsorships_' . $action)) {
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
            case 'save_visibility_stats':
                $this->handle_save_visibility_stats();
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
        $request = wp_unslash($_POST);

        $id = $this->repo->upsert_package(array(
            'id' => $request['id'] ?? 0,
            'name' => $request['name'] ?? '',
            'slug' => $request['slug'] ?? '',
            'scope' => $request['scope'] ?? 'event',
            'base_price' => $request['base_price'] ?? 0,
            'active' => !empty($request['active']),
            'includes_physical_banner' => !empty($request['includes_physical_banner']),
            'counts_toward_banner_cap' => !empty($request['counts_toward_banner_cap']),
            'requires_approval' => !empty($request['requires_approval']),
            'public_display_enabled' => !empty($request['public_display_enabled']),
            'email_display_enabled' => !empty($request['email_display_enabled']),
            'report_included' => !empty($request['report_included']),
            'required_assets' => $request['required_assets'] ?? '',
            'fulfillment_template' => $request['fulfillment_template'] ?? '',
            'description' => $request['description'] ?? '',
        ));

        if (is_wp_error($id)) {
            $this->redirect_error('save_failed');
        }

        $this->redirect_message('saved', 'vms-sponsorships-packages');
    }

    private function handle_update_application_status() {
        $request = wp_unslash($_POST);
        $id = absint($request['application_id'] ?? 0);
        $status = sanitize_key($request['status'] ?? 'submitted');
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $application = $this->repo->get_application($id);
        if (!$application) {
            $this->redirect_error('application_missing');
        }

        $result = $this->repo->update_application_status($id, $status, $request['decline_reason_private'] ?? '');
        if (false === $result) {
            $this->redirect_error('save_failed');
        }

        $application = $this->repo->get_application($id);

        if ($application) {
            $this->notifications->application_status_changed($application);
        }

        $this->redirect_message('status_updated', 'vms-sponsorships-applications');
    }

    private function handle_save_assignment() {
        $request = wp_unslash($_POST);
        $id = absint($request['id'] ?? 0);
        $application_id = isset($request['application_id']) ? absint($request['application_id']) : 0;
        $source_application = $application_id ? $this->repo->get_application($application_id) : null;

        if ($application_id > 0 && !$source_application) {
            $this->redirect_error('application_missing');
        }

        $data = array(
            'assignment_scope' => $request['assignment_scope'] ?? 'event',
            'event_id' => $request['event_id'] ?? null,
            'scope_label' => $request['scope_label'] ?? '',
            'starts_at' => $request['starts_at'] ?? '',
            'ends_at' => $request['ends_at'] ?? '',
            'package_id' => $request['package_id'] ?? null,
            'slot_key' => $request['slot_key'] ?? 'presenting',
            'status' => $request['status'] ?? 'prospect',
            'sponsor_display_name' => $request['sponsor_display_name'] ?? '',
            'sponsor_tagline' => $request['sponsor_tagline'] ?? '',
            'sponsor_url' => $request['sponsor_url'] ?? '',
            'amount' => $request['amount'] ?? 0,
            'in_kind_value' => $request['in_kind_value'] ?? 0,
            'payment_status' => $request['payment_status'] ?? 'unpaid',
            'public_display_enabled' => !empty($request['public_display_enabled']),
            'placeholder_enabled' => !empty($request['placeholder_enabled']),
            'physical_banner_included' => !empty($request['physical_banner_included']),
            'asset_status' => $request['asset_status'] ?? 'not_submitted',
            'fulfillment_status' => $request['fulfillment_status'] ?? 'not_started',
            'internal_notes' => $request['internal_notes'] ?? '',
        );

        foreach (array('vendor_id', 'user_id', 'application_id', 'season_id', 'banner_slot', 'report_status') as $field) {
            if (array_key_exists($field, $request)) {
                $data[$field] = $request[$field];
            }
        }

        if ($source_application) {
            $data['application_id'] = $source_application->id;

            if (empty($data['vendor_id']) && !empty($source_application->vendor_id)) {
                $data['vendor_id'] = $source_application->vendor_id;
            }

            if (empty($data['user_id']) && !empty($source_application->user_id)) {
                $data['user_id'] = $source_application->user_id;
            }

            if (empty($data['season_id']) && !empty($source_application->season_id)) {
                $data['season_id'] = $source_application->season_id;
            }
        }

        $result = $id ? $this->repo->update_assignment($id, $data) : $this->repo->create_assignment($data);

        if (is_wp_error($result)) {
            if ('vms_sponsorships_duplicate_application_assignment' === $result->get_error_code()) {
                $error_data = $result->get_error_data();
                $existing_assignment_id = is_array($error_data) ? absint($error_data['assignment_id'] ?? 0) : 0;
                if ($existing_assignment_id > 0) {
                    $this->redirect_message('assignment_exists', 'vms-sponsorships-assignments', array(
                        'assignment_id' => $existing_assignment_id,
                    ));
                }
            }
            if ('vms_sponsorships_banner_cap_exceeded' === $result->get_error_code()) {
                $this->redirect_error('banner_cap');
            }
            if ('vms_sponsorships_assignment_scope_dates_missing' === $result->get_error_code()) {
                $this->redirect_error('assignment_scope_dates_missing');
            }
            if ('vms_sponsorships_assignment_scope_dates_invalid' === $result->get_error_code()) {
                $this->redirect_error('assignment_scope_dates_invalid');
            }
            if ('vms_sponsorships_assignment_scope_conflict' === $result->get_error_code()) {
                $this->redirect_error('assignment_scope_conflict');
            }
            if ('vms_sponsorships_primary_scope_conflict' === $result->get_error_code()) {
                $this->redirect_error('primary_scope_conflict');
            }
            $this->redirect_error('save_failed');
        }

        $assignment_id = $id ?: absint($result);
        $approve_after_create = !$id && !empty($request['approve_application_after_create']);

        if ($approve_after_create && $source_application && sanitize_key($source_application->status) !== 'approved') {
            $approval_result = $this->repo->update_application_status($source_application->id, 'approved');
            $updated_application = $this->repo->get_application($source_application->id);

            if (false === $approval_result || !$updated_application) {
                $this->redirect_error('application_approval_failed', array(
                    'page' => 'vms-sponsorships-assignments',
                    'assignment_id' => $assignment_id,
                ));
            }

            $this->notifications->application_status_changed($updated_application);

            $this->redirect_message('assignment_created_approved', 'vms-sponsorships-assignments', array(
                'assignment_id' => $assignment_id,
            ));
        }

        $this->redirect_message($id ? 'assignment_saved' : 'assignment_created', 'vms-sponsorships-assignments', array(
            'assignment_id' => $assignment_id,
        ));
    }

    private function handle_save_settings() {
        $banner_defaults = VMS_Sponsorships_Install::default_unsold_banner_settings();
        $request = wp_unslash($_POST);
        $desktop_banner_image_id = absint($request['banner_desktop_image_id'] ?? ($request['banner_image_id'] ?? 0));
        $mobile_banner_image_id = absint($request['banner_mobile_image_id'] ?? 0);

        update_option('vms_sponsorships_max_event_banners', max(0, absint($request['max_event_banners'] ?? 1)));
        update_option('vms_sponsorships_placeholder_heading', sanitize_text_field($request['placeholder_heading'] ?? ''));
        update_option('vms_sponsorships_placeholder_body', sanitize_textarea_field($request['placeholder_body'] ?? ''));
        update_option('vms_sponsorships_placeholder_button', sanitize_text_field($request['placeholder_button'] ?? ''));
        update_option('vms_sponsorships_inquiry_page_url', esc_url_raw($request['inquiry_page_url'] ?? ''));
        update_option('vms_sponsorships_banner_headline', sanitize_text_field($request['banner_headline'] ?? $banner_defaults['vms_sponsorships_banner_headline']));
        update_option('vms_sponsorships_banner_body', sanitize_textarea_field($request['banner_body'] ?? $banner_defaults['vms_sponsorships_banner_body']));
        update_option('vms_sponsorships_banner_cta_text', sanitize_text_field($request['banner_cta_text'] ?? $banner_defaults['vms_sponsorships_banner_cta_text']));
        update_option('vms_sponsorships_banner_cta_url', esc_url_raw($request['banner_cta_url'] ?? ''));
        update_option('vms_sponsorships_banner_desktop_image_id', $desktop_banner_image_id);
        update_option('vms_sponsorships_banner_mobile_image_id', $mobile_banner_image_id);
        update_option('vms_sponsorships_banner_image_id', $desktop_banner_image_id);
        update_option('vms_sponsorships_event_page_placement_mode', $this->sanitize_event_page_placement_mode($request['event_page_placement_mode'] ?? VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_MANUAL));

        $this->repo->bust_display_cache();
        $this->redirect_message('settings_saved', 'vms-sponsorships-settings');
    }

    private function handle_save_visibility_stats() {
        $request = wp_unslash($_POST);

        VMS_Sponsorships_Visibility_Stats::save_from_request($request);

        $this->repo->bust_display_cache();
        $this->redirect_message('visibility_saved', 'vms-sponsorships-visibility');
    }

    private function handle_update_asset_status() {
        $request = wp_unslash($_POST);
        $id = absint($request['asset_id'] ?? 0);
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $previous_asset = $this->repo->get_asset($id);
        if (!$previous_asset) {
            $this->redirect_error('missing_data');
        }

        $result = $this->repo->update_asset_status($id, $request['status'] ?? 'pending_review', $request['rejection_note'] ?? '');
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
        $request = wp_unslash($_POST);
        $id = absint($request['fulfillment_item_id'] ?? 0);
        if (!$id) {
            $this->redirect_error('missing_data');
        }

        $result = $this->repo->update_fulfillment_item($id, $request['status'] ?? 'not_started', $request['notes'] ?? '');
        if (false === $result) {
            $this->redirect_error('save_failed');
        }

        $this->redirect_message('saved', 'vms-sponsorships-assignments');
    }

    public function render_dashboard() {
        $pending_application_statuses = array('submitted', 'under_review', 'info_requested', 'waitlisted');
        $pending_applications = $this->repo->get_applications(array(
            'status' => $pending_application_statuses,
            'limit' => 6,
            'exclude_linked_assignments' => true,
        ));
        $pending_application_count = $this->repo->count_applications(array(
            'status' => $pending_application_statuses,
            'exclude_linked_assignments' => true,
        ));
        $approved_unassigned_applications = $this->repo->get_applications(array(
            'status' => 'approved',
            'limit' => 6,
            'exclude_linked_assignments' => true,
        ));
        $approved_unassigned_count = $this->repo->count_applications(array(
            'status' => 'approved',
            'exclude_linked_assignments' => true,
        ));
        $applications_needing_action_count = $pending_application_count + $approved_unassigned_count;

        $pending_assets = $this->repo->get_assets(array(
            'status' => 'pending_review',
            'limit' => 6,
        ));
        $pending_asset_count = $this->repo->count_assets(array('status' => 'pending_review'));

        $active_assignment_filters = array(
            'status__not_in' => array('declined', 'cancelled', 'archived'),
        );
        $active_assignments = $this->repo->get_assignments(array_merge($active_assignment_filters, array('limit' => 6)));
        $active_assignment_count = $this->repo->count_assignments($active_assignment_filters);

        $visibility = $this->visibility_snapshot();
        ?>
        <div class="wrap vms-sponsorships-dashboard-wrap">
            <?php $this->render_page_header(__('VMS Sponsorships', 'vms-sponsorships'), __('Operator workspace for sponsorship intake, asset review, assignments, packages, and sponsor visibility stats.', 'vms-sponsorships')); ?>

            <style>
                .vms-sponsorships-dashboard {
                    max-width: 1220px;
                }
                .vms-sponsorships-dashboard-grid,
                .vms-sponsorships-dashboard-panels {
                    display: grid;
                    gap: 16px;
                }
                .vms-sponsorships-dashboard-grid {
                    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                    margin: 18px 0 16px;
                }
                .vms-sponsorships-dashboard-panels {
                    align-items: stretch;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .vms-sponsorships-card,
                .vms-sponsorships-panel {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
                }
                .vms-sponsorships-card {
                    padding: 18px;
                }
                .vms-sponsorships-card__eyebrow {
                    color: #50575e;
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    letter-spacing: 0.04em;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                }
                .vms-sponsorships-card__metric {
                    color: #111827;
                    display: block;
                    font-size: 34px;
                    font-weight: 700;
                    line-height: 1.1;
                    margin-bottom: 8px;
                }
                .vms-sponsorships-card__body,
                .vms-sponsorships-card__meta {
                    color: #4b5563;
                    margin: 0 0 12px;
                }
                .vms-sponsorships-panel {
                    display: flex;
                    flex-direction: column;
                    min-width: 0;
                    overflow: hidden;
                }
                .vms-sponsorships-panel__header {
                    align-items: center;
                    border-bottom: 1px solid #eaecf0;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 16px 18px;
                }
                .vms-sponsorships-panel__header h2 {
                    margin: 0;
                }
                .vms-sponsorships-panel__body {
                    display: flex;
                    flex: 1 1 auto;
                    flex-direction: column;
                    gap: 14px;
                    min-height: 0;
                    padding: 14px 18px 18px;
                }
                .vms-sponsorships-panel__body > :first-child {
                    margin-top: 0;
                }
                .vms-sponsorships-panel__body > :last-child {
                    margin-bottom: 0;
                }
                .vms-sponsorships-panel__scroll-shell {
                    flex: 1 1 auto;
                    min-height: 0;
                    position: relative;
                }
                .vms-sponsorships-panel__scroll-shell::after {
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.94) 56%, #fff 100%);
                    bottom: 0;
                    content: "";
                    left: 0;
                    opacity: 0;
                    pointer-events: none;
                    position: absolute;
                    right: 4px;
                    top: auto;
                    transition: opacity 0.18s ease;
                    height: 54px;
                }
                .vms-sponsorships-panel__scroll-shell.is-overflowing:not(.is-scroll-end)::after {
                    opacity: 1;
                }
                .vms-sponsorships-panel__scroll {
                    height: 100%;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: hidden;
                    overscroll-behavior: contain;
                    padding-right: 4px;
                    scrollbar-gutter: stable;
                }
                .vms-sponsorships-panel__scroll-helper {
                    align-items: center;
                    color: #6b7280;
                    display: flex;
                    font-size: 11px;
                    font-weight: 600;
                    gap: 8px;
                    letter-spacing: 0.05em;
                    max-height: 0;
                    opacity: 0;
                    overflow: hidden;
                    text-transform: uppercase;
                    transition: max-height 0.18s ease, margin-top 0.18s ease, opacity 0.18s ease;
                }
                .vms-sponsorships-panel__scroll-helper::before {
                    background: #cbd5e1;
                    content: "";
                    display: block;
                    flex: 0 0 24px;
                    height: 1px;
                }
                .vms-sponsorships-panel__scroll-shell.is-overflowing:not(.is-scroll-end) + .vms-sponsorships-panel__scroll-helper {
                    margin-top: -2px;
                    max-height: 24px;
                    opacity: 1;
                }
                .vms-sponsorships-summary-list {
                    display: grid;
                    gap: 0;
                }
                .vms-sponsorships-panel-groups {
                    display: grid;
                    gap: 18px;
                }
                .vms-sponsorships-panel-group {
                    display: grid;
                    gap: 12px;
                }
                .vms-sponsorships-panel-group + .vms-sponsorships-panel-group {
                    border-top: 1px solid #eaecf0;
                    padding-top: 18px;
                }
                .vms-sponsorships-panel-group__header {
                    align-items: center;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    justify-content: space-between;
                }
                .vms-sponsorships-panel-group__header h3 {
                    color: #111827;
                    font-size: 14px;
                    line-height: 1.35;
                    margin: 0;
                }
                .vms-sponsorships-panel-group__meta {
                    color: #6b7280;
                    font-size: 12px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .vms-sponsorships-summary-row {
                    align-items: start;
                    border-top: 1px solid #eaecf0;
                    display: grid;
                    gap: 12px;
                    grid-template-columns: minmax(0, 1fr) auto;
                    padding: 14px 0;
                }
                .vms-sponsorships-summary-row:first-child {
                    border-top: 0;
                    padding-top: 0;
                }
                .vms-sponsorships-summary-row:last-child {
                    padding-bottom: 0;
                }
                .vms-sponsorships-summary-row--link {
                    border-radius: 10px;
                    color: inherit;
                    cursor: pointer;
                    margin-left: -8px;
                    margin-right: -8px;
                    padding-left: 8px;
                    padding-right: 8px;
                    text-decoration: none;
                    transition: background-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
                }
                .vms-sponsorships-summary-row--link:hover {
                    background: #f8fafc;
                }
                .vms-sponsorships-summary-row--link:focus-visible {
                    background: #f0f9ff;
                    box-shadow: inset 0 0 0 2px #2271b1;
                    outline: none;
                }
                .vms-sponsorships-summary-row--link:hover .vms-sponsorships-summary-title,
                .vms-sponsorships-summary-row--link:focus-visible .vms-sponsorships-summary-title {
                    color: #135e96;
                }
                .vms-sponsorships-summary-main,
                .vms-sponsorships-summary-side,
                .vms-sponsorships-summary-meta > span,
                .vms-sponsorships-summary-note {
                    min-width: 0;
                }
                .vms-sponsorships-summary-title {
                    color: #111827;
                    display: block;
                    font-size: 14px;
                    font-weight: 600;
                    line-height: 1.35;
                    margin: 0;
                }
                .vms-sponsorships-summary-title a {
                    color: inherit;
                    text-decoration: none;
                }
                .vms-sponsorships-summary-title a:hover {
                    text-decoration: underline;
                }
                .vms-sponsorships-summary-meta {
                    color: #6b7280;
                    display: flex;
                    flex-wrap: wrap;
                    font-size: 13px;
                    gap: 4px 10px;
                    margin-top: 6px;
                }
                .vms-sponsorships-summary-meta a {
                    color: #2271b1;
                    text-decoration: none;
                }
                .vms-sponsorships-summary-meta a:hover {
                    text-decoration: underline;
                }
                .vms-sponsorships-summary-note {
                    color: #4b5563;
                    font-size: 13px;
                    line-height: 1.45;
                    margin: 6px 0 0;
                    overflow-wrap: anywhere;
                }
                .vms-sponsorships-summary-side {
                    display: grid;
                    gap: 8px;
                    justify-items: end;
                    min-width: 118px;
                    text-align: right;
                }
                .vms-sponsorships-summary-badges {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    justify-content: flex-end;
                }
                .vms-sponsorships-summary-date {
                    color: #6b7280;
                    font-size: 12px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .vms-sponsorships-summary-action {
                    color: #2271b1;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .vms-sponsorships-empty {
                    color: #6b7280;
                    display: grid;
                    justify-items: center;
                    min-height: 100%;
                    padding: 24px 12px;
                    place-content: center;
                    text-align: center;
                }
                .vms-sponsorships-empty p {
                    margin: 0;
                    max-width: 24rem;
                }
                .vms-sponsorships-pill {
                    border-radius: 999px;
                    display: inline-block;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1;
                    padding: 6px 10px;
                    white-space: nowrap;
                }
                .vms-sponsorships-pill--positive {
                    background: #dcfce7;
                    color: #166534;
                }
                .vms-sponsorships-pill--attention {
                    background: #dbeafe;
                    color: #1d4ed8;
                }
                .vms-sponsorships-pill--warning {
                    background: #fef3c7;
                    color: #92400e;
                }
                .vms-sponsorships-pill--negative {
                    background: #fee2e2;
                    color: #b91c1c;
                }
                .vms-sponsorships-pill--neutral {
                    background: #e5e7eb;
                    color: #374151;
                }
                .vms-sponsorships-visibility-list {
                    display: grid;
                    gap: 10px;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    margin: 0;
                }
                .vms-sponsorships-visibility-list dt {
                    color: #6b7280;
                    font-size: 12px;
                    font-weight: 600;
                    margin: 0 0 4px;
                    text-transform: uppercase;
                }
                .vms-sponsorships-visibility-list dd {
                    color: #111827;
                    margin: 0;
                    overflow-wrap: anywhere;
                }
                .vms-sponsorships-visibility-list > div {
                    background: #f8fafc;
                    border: 1px solid #eaecf0;
                    border-radius: 10px;
                    min-width: 0;
                    padding: 12px;
                }
                .vms-sponsorships-muted {
                    color: #6b7280;
                }
                .vms-sponsorships-callout {
                    border-radius: 10px;
                    border: 1px solid #dbeafe;
                    background: #eff6ff;
                    color: #1d4ed8;
                    margin: 0;
                    padding: 12px 14px;
                }
                .vms-sponsorships-callout--warning {
                    border-color: #fde68a;
                    background: #fffbeb;
                    color: #92400e;
                }
                .vms-sponsorships-callout--neutral {
                    border-color: #e5e7eb;
                    background: #f8fafc;
                    color: #475569;
                }
                .vms-sponsorships-empty--stacked {
                    gap: 14px;
                }
                .vms-sponsorships-empty--stacked .button {
                    margin: 0 auto;
                }
                .vms-sponsorships-empty--compact {
                    justify-items: start;
                    min-height: 0;
                    padding: 0;
                    place-content: start;
                    text-align: left;
                }
                .vms-sponsorships-visibility-preview-grid {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .vms-sponsorships-visibility-preview-card {
                    background: #f8fafc;
                    border: 1px solid #eaecf0;
                    border-radius: 10px;
                    min-width: 0;
                    padding: 12px;
                }
                .vms-sponsorships-visibility-preview-label {
                    color: #6b7280;
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    margin: 0 0 6px;
                    text-transform: uppercase;
                }
                .vms-sponsorships-visibility-preview-value {
                    color: #111827;
                    display: block;
                    line-height: 1.5;
                    overflow-wrap: anywhere;
                }
                .vms-sponsorships-visibility-preview-meta {
                    display: grid;
                    gap: 8px;
                }
                .vms-sponsorships-visibility-preview-meta p {
                    color: #4b5563;
                    margin: 0;
                }
                @media (min-width: 1100px) {
                    .vms-sponsorships-panel {
                        height: clamp(26rem, 48vh, 30rem);
                    }
                }
                @media (max-width: 1099px) {
                    .vms-sponsorships-dashboard-panels {
                        grid-template-columns: 1fr;
                    }
                }
                @media (max-width: 640px) {
                    .vms-sponsorships-summary-row {
                        grid-template-columns: 1fr;
                    }
                    .vms-sponsorships-summary-side {
                        justify-items: start;
                        min-width: 0;
                        text-align: left;
                    }
                    .vms-sponsorships-summary-badges {
                        justify-content: flex-start;
                    }
                    .vms-sponsorships-visibility-list {
                        grid-template-columns: 1fr;
                    }
                    .vms-sponsorships-visibility-preview-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <div class="vms-sponsorships-dashboard">
                <div class="vms-sponsorships-dashboard-grid">
                    <section class="vms-sponsorships-card">
                        <span class="vms-sponsorships-card__eyebrow"><?php esc_html_e('Applications Needing Action', 'vms-sponsorships'); ?></span>
                        <span class="vms-sponsorships-card__metric"><?php echo esc_html(number_format_i18n($applications_needing_action_count)); ?></span>
                        <p class="vms-sponsorships-card__body"><?php esc_html_e('Unlinked sponsorship applications waiting on review or already approved and ready for assignment creation.', 'vms-sponsorships'); ?></p>
                        <a class="button button-primary" href="<?php echo esc_url($this->page_url('vms-sponsorships-applications')); ?>"><?php esc_html_e('Open Applications', 'vms-sponsorships'); ?></a>
                    </section>

                    <section class="vms-sponsorships-card">
                        <span class="vms-sponsorships-card__eyebrow"><?php esc_html_e('Assets Pending Review', 'vms-sponsorships'); ?></span>
                        <span class="vms-sponsorships-card__metric"><?php echo esc_html(number_format_i18n($pending_asset_count)); ?></span>
                        <p class="vms-sponsorships-card__body"><?php esc_html_e('Uploaded logos and sponsor creative waiting on operator review.', 'vms-sponsorships'); ?></p>
                        <a class="button button-primary" href="<?php echo esc_url($this->page_url('vms-sponsorships-assets')); ?>"><?php esc_html_e('Review Assets', 'vms-sponsorships'); ?></a>
                    </section>

                    <section class="vms-sponsorships-card">
                        <span class="vms-sponsorships-card__eyebrow"><?php esc_html_e('Active Assignments', 'vms-sponsorships'); ?></span>
                        <span class="vms-sponsorships-card__metric"><?php echo esc_html(number_format_i18n($active_assignment_count)); ?></span>
                        <p class="vms-sponsorships-card__body"><?php esc_html_e('Open, prospect, confirmed, paid, and fulfilled sponsorship records.', 'vms-sponsorships'); ?></p>
                        <a class="button button-primary" href="<?php echo esc_url($this->page_url('vms-sponsorships-assignments')); ?>"><?php esc_html_e('Manage Assignments', 'vms-sponsorships'); ?></a>
                    </section>

                    <section class="vms-sponsorships-card">
                        <span class="vms-sponsorships-card__eyebrow"><?php esc_html_e('Visibility Stats Freshness', 'vms-sponsorships'); ?></span>
                        <span class="vms-sponsorships-card__metric"><?php echo esc_html(number_format_i18n($visibility['stat_count'])); ?></span>
                        <p class="vms-sponsorships-card__body">
                            <?php
                            echo esc_html(
                                $visibility['has_stats']
                                    ? sprintf(
                                        _n('%d public proof point live.', '%d public proof points live.', $visibility['stat_count'], 'vms-sponsorships'),
                                        number_format_i18n($visibility['stat_count'])
                                    )
                                    : __('No public proof points are live yet.', 'vms-sponsorships')
                            );
                            ?>
                        </p>
                        <div><?php echo $this->state_pill_html($visibility['label'], $visibility['tone']); ?></div>
                        <p class="vms-sponsorships-card__meta"><?php echo esc_html($visibility['detail']); ?></p>
                        <a class="button" href="<?php echo esc_url($this->page_url('vms-sponsorships-visibility')); ?>"><?php esc_html_e('Update Visibility Stats', 'vms-sponsorships'); ?></a>
                    </section>
                </div>

                <div class="vms-sponsorships-dashboard-panels">
                    <section class="vms-sponsorships-panel">
                        <div class="vms-sponsorships-panel__header">
                            <h2><?php esc_html_e('Applications Needing Action', 'vms-sponsorships'); ?></h2>
                            <a class="button button-secondary" href="<?php echo esc_url($this->page_url('vms-sponsorships-applications')); ?>"><?php esc_html_e('Full Queue', 'vms-sponsorships'); ?></a>
                        </div>
                        <div class="vms-sponsorships-panel__body">
                            <div class="vms-sponsorships-panel__scroll-shell" role="region" aria-label="<?php esc_attr_e('Sponsorship applications needing action', 'vms-sponsorships'); ?>">
                                <div class="vms-sponsorships-panel__scroll">
                                    <div class="vms-sponsorships-panel-groups">
                                        <section class="vms-sponsorships-panel-group" aria-labelledby="vms-sponsorships-dashboard-pending-applications">
                                            <div class="vms-sponsorships-panel-group__header">
                                                <h3 id="vms-sponsorships-dashboard-pending-applications"><?php esc_html_e('New / Pending Review', 'vms-sponsorships'); ?></h3>
                                                <span class="vms-sponsorships-panel-group__meta"><?php echo esc_html(sprintf(_n('%d unlinked application', '%d unlinked applications', $pending_application_count, 'vms-sponsorships'), number_format_i18n($pending_application_count))); ?></span>
                                            </div>
                                            <?php if (empty($pending_applications)) : ?>
                                                <div class="vms-sponsorships-empty vms-sponsorships-empty--compact"><p><?php esc_html_e('No unlinked applications are waiting on review right now.', 'vms-sponsorships'); ?></p></div>
                                            <?php else : ?>
                                                <div class="vms-sponsorships-summary-list">
                                                    <?php foreach ($pending_applications as $application) : ?>
                                                        <?php
                                                        $application_context = array();
                                                        if ($application->requested_package_id) {
                                                            $application_context[] = $this->package_name($application->requested_package_id);
                                                        }
                                                        $application_context[] = $this->scope_label($application->scope ?? 'event');
                                                        if (!empty($application->requested_slot_key)) {
                                                            $application_context[] = $this->slot_label($application->requested_slot_key);
                                                        }
                                                        $application_review_url = $this->application_review_url($application->id);
                                                        ?>
                                                        <a class="vms-sponsorships-summary-row vms-sponsorships-summary-row--link" href="<?php echo esc_url($application_review_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open application review for %s', 'vms-sponsorships'), $application->business_name)); ?>">
                                                            <div class="vms-sponsorships-summary-main">
                                                                <strong class="vms-sponsorships-summary-title"><?php echo esc_html($application->business_name); ?></strong>
                                                                <div class="vms-sponsorships-summary-meta">
                                                                    <span><?php echo esc_html($application->contact_name ?: $application->email); ?></span>
                                                                    <?php if ($application->contact_name && $application->email) : ?>
                                                                        <span><?php echo esc_html($application->email); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <p class="vms-sponsorships-summary-note"><?php echo esc_html(implode(' · ', $application_context)); ?></p>
                                                            </div>
                                                            <div class="vms-sponsorships-summary-side">
                                                                <?php echo $this->status_pill_html($application->status); ?>
                                                                <span class="vms-sponsorships-summary-date"><?php echo esc_html($this->format_date($application->submitted_at)); ?></span>
                                                                <span class="vms-sponsorships-summary-action" aria-hidden="true"><?php esc_html_e('Open Review', 'vms-sponsorships'); ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>

                                        <?php if (!empty($approved_unassigned_applications)) : ?>
                                            <section class="vms-sponsorships-panel-group" aria-labelledby="vms-sponsorships-dashboard-approved-applications">
                                                <div class="vms-sponsorships-panel-group__header">
                                                    <h3 id="vms-sponsorships-dashboard-approved-applications"><?php esc_html_e('Approved / Needs Assignment', 'vms-sponsorships'); ?></h3>
                                                    <span class="vms-sponsorships-panel-group__meta"><?php echo esc_html(sprintf(_n('%d approved application', '%d approved applications', $approved_unassigned_count, 'vms-sponsorships'), number_format_i18n($approved_unassigned_count))); ?></span>
                                                </div>
                                                <div class="vms-sponsorships-summary-list">
                                                    <?php foreach ($approved_unassigned_applications as $application) : ?>
                                                        <?php
                                                        $application_context = array();
                                                        if ($application->requested_package_id) {
                                                            $application_context[] = $this->package_name($application->requested_package_id);
                                                        }
                                                        $application_context[] = $this->scope_label($application->scope ?? 'event');
                                                        if (!empty($application->requested_slot_key)) {
                                                            $application_context[] = $this->slot_label($application->requested_slot_key);
                                                        }
                                                        $application_context[] = __('Approved and ready for assignment creation.', 'vms-sponsorships');
                                                        $application_review_url = $this->application_review_url($application->id);
                                                        ?>
                                                        <a class="vms-sponsorships-summary-row vms-sponsorships-summary-row--link" href="<?php echo esc_url($application_review_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open approved application for %s', 'vms-sponsorships'), $application->business_name)); ?>">
                                                            <div class="vms-sponsorships-summary-main">
                                                                <strong class="vms-sponsorships-summary-title"><?php echo esc_html($application->business_name); ?></strong>
                                                                <div class="vms-sponsorships-summary-meta">
                                                                    <span><?php echo esc_html($application->contact_name ?: $application->email); ?></span>
                                                                    <?php if ($application->contact_name && $application->email) : ?>
                                                                        <span><?php echo esc_html($application->email); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <p class="vms-sponsorships-summary-note"><?php echo esc_html(implode(' · ', $application_context)); ?></p>
                                                            </div>
                                                            <div class="vms-sponsorships-summary-side">
                                                                <div class="vms-sponsorships-summary-badges">
                                                                    <?php echo $this->status_pill_html($application->status); ?>
                                                                    <?php echo $this->state_pill_html(__('Needs assignment', 'vms-sponsorships'), 'warning'); ?>
                                                                </div>
                                                                <span class="vms-sponsorships-summary-date"><?php echo esc_html($this->format_date($application->submitted_at)); ?></span>
                                                                <span class="vms-sponsorships-summary-action" aria-hidden="true"><?php esc_html_e('Open Review', 'vms-sponsorships'); ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="vms-sponsorships-panel__scroll-helper" aria-hidden="true"><?php esc_html_e('Scroll for more', 'vms-sponsorships'); ?></div>
                        </div>
                    </section>

                    <section class="vms-sponsorships-panel">
                        <div class="vms-sponsorships-panel__header">
                            <h2><?php esc_html_e('Pending Assets', 'vms-sponsorships'); ?></h2>
                            <a class="button button-secondary" href="<?php echo esc_url($this->page_url('vms-sponsorships-assets')); ?>"><?php esc_html_e('Open Review', 'vms-sponsorships'); ?></a>
                        </div>
                        <div class="vms-sponsorships-panel__body">
                            <div class="vms-sponsorships-panel__scroll-shell" role="region" aria-label="<?php esc_attr_e('Pending sponsor assets', 'vms-sponsorships'); ?>">
                                <div class="vms-sponsorships-panel__scroll">
                                    <?php if (empty($pending_assets)) : ?>
                                        <div class="vms-sponsorships-empty"><p><?php esc_html_e('No sponsor assets are waiting on review.', 'vms-sponsorships'); ?></p></div>
                                    <?php else : ?>
                                        <div class="vms-sponsorships-summary-list">
                                            <?php foreach ($pending_assets as $asset) : ?>
                                                <?php
                                                $asset_review_url = $this->asset_review_url($asset->id);
                                                $asset_assignment_label = $asset->assignment_id
                                                    ? sprintf(__('Assignment #%d', 'vms-sponsorships'), absint($asset->assignment_id))
                                                    : __('Not linked yet', 'vms-sponsorships');
                                                ?>
                                                <a class="vms-sponsorships-summary-row vms-sponsorships-summary-row--link" href="<?php echo esc_url($asset_review_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open asset review for %s', 'vms-sponsorships'), ucwords(str_replace('_', ' ', $asset->asset_type)))); ?>">
                                                    <div class="vms-sponsorships-summary-main">
                                                        <strong class="vms-sponsorships-summary-title"><?php echo esc_html(ucwords(str_replace('_', ' ', $asset->asset_type))); ?></strong>
                                                        <div class="vms-sponsorships-summary-meta">
                                                            <span><?php echo esc_html($asset_assignment_label); ?></span>
                                                        </div>
                                                        <p class="vms-sponsorships-summary-note"><?php esc_html_e('Waiting on operator review before placement goes live.', 'vms-sponsorships'); ?></p>
                                                    </div>
                                                    <div class="vms-sponsorships-summary-side">
                                                        <?php echo $this->status_pill_html($asset->status); ?>
                                                        <span class="vms-sponsorships-summary-date"><?php echo esc_html($this->format_date($asset->uploaded_at)); ?></span>
                                                        <span class="vms-sponsorships-summary-action" aria-hidden="true"><?php esc_html_e('Open Review', 'vms-sponsorships'); ?></span>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="vms-sponsorships-panel__scroll-helper" aria-hidden="true"><?php esc_html_e('Scroll for more', 'vms-sponsorships'); ?></div>
                        </div>
                    </section>

                    <section class="vms-sponsorships-panel">
                        <div class="vms-sponsorships-panel__header">
                            <h2><?php esc_html_e('Recent / Active Assignments', 'vms-sponsorships'); ?></h2>
                            <a class="button button-secondary" href="<?php echo esc_url($this->page_url('vms-sponsorships-assignments')); ?>"><?php esc_html_e('All Assignments', 'vms-sponsorships'); ?></a>
                        </div>
                        <div class="vms-sponsorships-panel__body">
                            <div class="vms-sponsorships-panel__scroll-shell" role="region" aria-label="<?php esc_attr_e('Recent and active sponsorship assignments', 'vms-sponsorships'); ?>">
                                <div class="vms-sponsorships-panel__scroll">
                                    <?php if (empty($active_assignments)) : ?>
                                        <div class="vms-sponsorships-empty"><p><?php esc_html_e('No active sponsorship assignments yet.', 'vms-sponsorships'); ?></p></div>
                                    <?php else : ?>
                                        <div class="vms-sponsorships-summary-list">
                                            <?php foreach ($active_assignments as $assignment) : ?>
                                                <?php
                                                $assignment_meta = array();
                                                if (!empty($assignment->package_id)) {
                                                    $assignment_meta[] = $this->package_name($assignment->package_id);
                                                }
                                                $assignment_meta[] = $this->slot_label($assignment->slot_key);
                                                $assignment_edit_url = $this->page_url('vms-sponsorships-assignments', array('assignment_id' => absint($assignment->id)));
                                                ?>
                                                <a class="vms-sponsorships-summary-row vms-sponsorships-summary-row--link" href="<?php echo esc_url($assignment_edit_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open assignment for %s', 'vms-sponsorships'), $assignment->sponsor_display_name)); ?>">
                                                    <div class="vms-sponsorships-summary-main">
                                                        <strong class="vms-sponsorships-summary-title"><?php echo esc_html($assignment->sponsor_display_name); ?></strong>
                                                        <div class="vms-sponsorships-summary-meta">
                                                            <span><?php echo esc_html(implode(' · ', $assignment_meta)); ?></span>
                                                        </div>
                                                        <p class="vms-sponsorships-summary-note"><?php echo esc_html($this->assignment_scope_summary($assignment)); ?></p>
                                                    </div>
                                                    <div class="vms-sponsorships-summary-side">
                                                        <div class="vms-sponsorships-summary-badges">
                                                            <?php echo $this->status_pill_html($assignment->status); ?>
                                                            <?php echo $this->status_pill_html($assignment->payment_status); ?>
                                                        </div>
                                                        <span class="vms-sponsorships-summary-action" aria-hidden="true"><?php esc_html_e('Open Assignment', 'vms-sponsorships'); ?></span>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="vms-sponsorships-panel__scroll-helper" aria-hidden="true"><?php esc_html_e('Scroll for more', 'vms-sponsorships'); ?></div>
                        </div>
                    </section>

                    <section class="vms-sponsorships-panel">
                        <div class="vms-sponsorships-panel__header">
                            <h2><?php esc_html_e('Visibility Stats Preview', 'vms-sponsorships'); ?></h2>
                            <a class="button button-secondary" href="<?php echo esc_url($this->page_url('vms-sponsorships-visibility')); ?>"><?php esc_html_e('Update Visibility Stats', 'vms-sponsorships'); ?></a>
                        </div>
                        <div class="vms-sponsorships-panel__body">
                            <p><?php echo $this->state_pill_html($visibility['label'], $visibility['tone']); ?></p>
                            <p class="description"><?php echo esc_html($visibility['detail']); ?></p>
                            <?php if (!$visibility['has_stats']) : ?>
                                <div class="vms-sponsorships-empty vms-sponsorships-empty--stacked">
                                    <p><?php esc_html_e('Visibility stats have not been configured yet.', 'vms-sponsorships'); ?></p>
                                    <a class="button button-primary" href="<?php echo esc_url($this->page_url('vms-sponsorships-visibility')); ?>"><?php esc_html_e('Update Visibility Stats', 'vms-sponsorships'); ?></a>
                                </div>
                            <?php else : ?>
                                <?php
                                $callout_tone = 'neutral';
                                if ($visibility['tone'] === 'warning') {
                                    $callout_tone = 'warning';
                                } elseif ($visibility['tone'] === 'attention') {
                                    $callout_tone = 'attention';
                                }
                                ?>
                                <?php if ($visibility['tone'] !== 'positive') : ?>
                                    <p class="vms-sponsorships-callout<?php echo $callout_tone === 'warning' ? ' vms-sponsorships-callout--warning' : ($callout_tone === 'neutral' ? ' vms-sponsorships-callout--neutral' : ''); ?>"><?php echo esc_html($visibility['attention_message']); ?></p>
                                <?php endif; ?>
                                <div class="vms-sponsorships-visibility-preview-grid" role="list" aria-label="<?php esc_attr_e('Visibility stats preview', 'vms-sponsorships'); ?>">
                                    <?php foreach ($visibility['stats'] as $stat) : ?>
                                        <article class="vms-sponsorships-visibility-preview-card" role="listitem">
                                            <span class="vms-sponsorships-visibility-preview-label"><?php echo esc_html($stat['label']); ?></span>
                                            <strong class="vms-sponsorships-visibility-preview-value"><?php echo esc_html($stat['value']); ?></strong>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <div class="vms-sponsorships-visibility-preview-meta">
                                    <?php if ($visibility['public_last_updated_text']) : ?>
                                        <p><strong><?php esc_html_e('Public date:', 'vms-sponsorships'); ?></strong> <?php echo esc_html($visibility['public_last_updated_text']); ?></p>
                                    <?php else : ?>
                                        <p><strong><?php esc_html_e('Public date:', 'vms-sponsorships'); ?></strong> <?php esc_html_e('Not set yet.', 'vms-sponsorships'); ?></p>
                                    <?php endif; ?>
                                    <p><strong><?php esc_html_e('Freshness guidance:', 'vms-sponsorships'); ?></strong> <?php esc_html_e('Update monthly or before sending sponsor proposals.', 'vms-sponsorships'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
            <script>
                (function () {
                    function initSponsorshipDashboardOverflow() {
                        var shells = document.querySelectorAll('.vms-sponsorships-dashboard .vms-sponsorships-panel__scroll-shell');

                        if (!shells.length) {
                            return;
                        }

                        function updateShell(shell) {
                            var scroll = shell.querySelector('.vms-sponsorships-panel__scroll');

                            if (!scroll) {
                                return;
                            }

                            shell.classList.remove('is-overflowing', 'is-scroll-end');

                            var overflow = (scroll.scrollHeight - scroll.clientHeight) > 6;
                            var atEnd = !overflow || ((scroll.scrollTop + scroll.clientHeight) >= (scroll.scrollHeight - 6));

                            shell.classList.toggle('is-overflowing', overflow);
                            shell.classList.toggle('is-scroll-end', atEnd);
                        }

                        function refresh() {
                            shells.forEach(updateShell);
                        }

                        shells.forEach(function (shell) {
                            var scroll = shell.querySelector('.vms-sponsorships-panel__scroll');

                            if (!scroll) {
                                return;
                            }

                            scroll.addEventListener('scroll', function () {
                                updateShell(shell);
                            }, { passive: true });
                        });

                        window.addEventListener('resize', refresh);

                        if (window.ResizeObserver) {
                            var observer = new ResizeObserver(refresh);
                            shells.forEach(function (shell) {
                                var scroll = shell.querySelector('.vms-sponsorships-panel__scroll');
                                observer.observe(shell);
                                if (scroll) {
                                    observer.observe(scroll);
                                }
                            });
                        }

                        requestAnimationFrame(refresh);
                        window.setTimeout(refresh, 150);
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initSponsorshipDashboardOverflow, { once: true });
                    } else {
                        initSponsorshipDashboardOverflow();
                    }
                }());
            </script>
        </div>
        <?php
    }

    private function visibility_snapshot() {
        return VMS_Sponsorships_Visibility_Stats::load();
    }

    private function status_pill_html($status) {
        $status = sanitize_key((string) $status);
        $label = $status !== '' ? ucwords(str_replace('_', ' ', $status)) : __('Unknown', 'vms-sponsorships');
        $tone = 'neutral';

        if (in_array($status, array('approved', 'confirmed', 'paid', 'fulfilled', 'complete'), true)) {
            $tone = 'positive';
        } elseif (in_array($status, array('submitted', 'under_review', 'info_requested', 'pending_review', 'open', 'prospect', 'pending', 'invoice_sent', 'in_progress'), true)) {
            $tone = 'attention';
        } elseif (in_array($status, array('needs_revision', 'waitlisted', 'unpaid', 'overdue', 'not_started', 'not_submitted'), true)) {
            $tone = 'warning';
        } elseif (in_array($status, array('not_required'), true)) {
            $tone = 'neutral';
        } elseif (in_array($status, array('declined', 'cancelled', 'rejected', 'archived', 'refunded'), true)) {
            $tone = 'negative';
        }

        return '<span class="vms-sponsorships-pill vms-sponsorships-pill--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    private function state_pill_html($label, $tone) {
        $tone = sanitize_key((string) $tone);
        if (!in_array($tone, array('positive', 'attention', 'warning', 'negative', 'neutral'), true)) {
            $tone = 'neutral';
        }

        return '<span class="vms-sponsorships-pill vms-sponsorships-pill--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    private function event_label($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return __('Not linked', 'vms-sponsorships');
        }

        $title = get_the_title($event_id);
        if (is_string($title) && $title !== '') {
            return $title;
        }

        return sprintf(__('Event #%d', 'vms-sponsorships'), $event_id);
    }

    private function format_datetime($value) {
        $value = (string) $value;
        if ($value === '') {
            return __('Not set', 'vms-sponsorships');
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function format_date($value) {
        $value = (string) $value;
        if ($value === '') {
            return __('Not set', 'vms-sponsorships');
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        return wp_date(get_option('date_format'), $timestamp);
    }

    private function render_focus_table_styles($args = array()) {
        $args = wp_parse_args($args, array(
            'highlight_row_id' => '',
        ));
        $highlight_row_id = is_string($args['highlight_row_id']) ? trim($args['highlight_row_id']) : '';
        ?>
        <style>
            .vms-sponsorships-focus-table-wrap {
                max-width: 100%;
                overflow-x: auto;
                scrollbar-gutter: stable;
            }
            .vms-sponsorships-focus-table tbody tr[id] td {
                transition: background-color 0.18s ease, box-shadow 0.18s ease;
            }
            .vms-sponsorships-focus-table tbody tr:target td,
            .vms-sponsorships-focus-table tbody tr.is-highlighted td {
                background: #eff6ff;
            }
            .vms-sponsorships-focus-table tbody tr:target td:first-child,
            .vms-sponsorships-focus-table tbody tr.is-highlighted td:first-child {
                box-shadow: inset 4px 0 0 #2271b1;
            }
            .vms-sponsorships-focus-table tbody tr.is-highlighted.is-highlighted-recent td {
                animation: vms-sponsorships-row-highlight 3.8s ease-out 1;
            }
            @keyframes vms-sponsorships-row-highlight {
                0% {
                    background: #dbeafe;
                }
                100% {
                    background: #eff6ff;
                }
            }
        </style>
        <?php if ($highlight_row_id !== '') : ?>
            <script>
                (function () {
                    var rowId = <?php echo wp_json_encode($highlight_row_id); ?>;

                    function resetHorizontalScroll(row) {
                        var wrap = row ? row.closest('.vms-sponsorships-focus-table-wrap') : null;
                        if (wrap) {
                            wrap.scrollLeft = 0;
                        }

                        if (document.scrollingElement) {
                            document.scrollingElement.scrollLeft = 0;
                        }
                        document.documentElement.scrollLeft = 0;
                        document.body.scrollLeft = 0;
                    }

                    function highlightFocusedRow() {
                        var row = document.getElementById(rowId);
                        if (!row) {
                            return;
                        }

                        row.classList.add('is-highlighted', 'is-highlighted-recent');
                        resetHorizontalScroll(row);

                        row.scrollIntoView({
                            block: 'center',
                            inline: 'nearest',
                            behavior: 'auto'
                        });

                        resetHorizontalScroll(row);

                        row.addEventListener('animationend', function () {
                            row.classList.remove('is-highlighted-recent');
                        }, { once: true });
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function () {
                            window.requestAnimationFrame(highlightFocusedRow);
                        }, { once: true });
                    } else {
                        window.requestAnimationFrame(highlightFocusedRow);
                    }
                }());
            </script>
        <?php endif; ?>
        <?php
    }

    public function render_applications() {
        $applications = $this->repo->get_applications('', 100);
        $application_ids = array_map('absint', wp_list_pluck($applications, 'id'));
        $assignment_map = $this->repo->get_assignment_map_by_application_ids($application_ids);
        $highlight_application_id = $this->highlighted_application_id();
        ?>
        <div class="wrap">
            <?php $this->render_page_header(__('Sponsorship Applications', 'vms-sponsorships'), __('Review sponsor applications, context, and consideration details before approval or follow-up.', 'vms-sponsorships')); ?>
            <?php
            $this->render_focus_table_styles(array(
                'highlight_row_id' => $highlight_application_id > 0 ? $this->application_anchor_id($highlight_application_id) : '',
            ));
            ?>
            <style>
                .vms-sponsorships-application-actions {
                    display: grid;
                    gap: 10px;
                    min-width: 240px;
                }
                .vms-sponsorships-application-actions form {
                    margin: 0;
                }
                .vms-sponsorships-application-actions select,
                .vms-sponsorships-application-actions textarea {
                    width: 100%;
                }
                .vms-sponsorships-application-actions textarea {
                    min-height: 72px;
                }
                .vms-sponsorships-application-action-links {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .vms-sponsorships-linked-assignment {
                    display: grid;
                    gap: 8px;
                }
                .vms-sponsorships-linked-assignment__badges {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }
                .vms-sponsorships-linked-assignment__meta {
                    color: #50575e;
                    margin: 0;
                }
                .vms-sponsorships-linked-assignment__empty {
                    color: #50575e;
                }
            </style>
            <div class="vms-sponsorships-focus-table-wrap">
                <table class="widefat striped vms-sponsorships-focus-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Business', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Contact', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Event', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Package / context', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Consideration', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Assignment', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Submitted', 'vms-sponsorships'); ?></th>
                            <th><?php esc_html_e('Action', 'vms-sponsorships'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)) : ?>
                            <tr><td colspan="9"><?php esc_html_e('No sponsorship applications yet.', 'vms-sponsorships'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($applications as $application) : ?>
                            <?php
                            $linked_assignment = $assignment_map[absint($application->id)] ?? null;
                            $assignment_url = $linked_assignment ? $this->assignment_edit_url($linked_assignment->id) : '';
                            $row_classes = array();
                            if ($highlight_application_id === absint($application->id)) {
                                $row_classes[] = 'is-highlighted';
                            }
                            if ($linked_assignment) {
                                $row_classes[] = 'has-linked-assignment';
                            }
                            ?>
                            <tr id="<?php echo esc_attr($this->application_anchor_id($application->id)); ?>" class="<?php echo esc_attr(implode(' ', $row_classes)); ?>">
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
                                <td><?php echo $this->status_pill_html($application->status); ?></td>
                                <td>
                                    <?php if ($linked_assignment) : ?>
                                        <div class="vms-sponsorships-linked-assignment">
                                            <div class="vms-sponsorships-linked-assignment__badges">
                                                <?php echo $this->state_pill_html(__('Linked assignment', 'vms-sponsorships'), 'neutral'); ?>
                                                <?php echo $this->status_pill_html($linked_assignment->status); ?>
                                                <?php echo $this->status_pill_html($linked_assignment->payment_status); ?>
                                            </div>
                                            <p class="vms-sponsorships-linked-assignment__meta"><?php echo esc_html($this->slot_label($linked_assignment->slot_key)); ?> · <?php echo esc_html($this->assignment_scope_summary($linked_assignment)); ?></p>
                                        </div>
                                    <?php else : ?>
                                        <span class="vms-sponsorships-linked-assignment__empty"><?php esc_html_e('No assignment yet.', 'vms-sponsorships'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($this->format_datetime($application->submitted_at)); ?></td>
                                <td>
                                    <div class="vms-sponsorships-application-actions">
                                        <form method="post">
                                            <?php wp_nonce_field('vms_sponsorships_update_application_status'); ?>
                                            <input type="hidden" name="vms_sponsorships_action" value="update_application_status">
                                            <input type="hidden" name="application_id" value="<?php echo esc_attr($application->id); ?>">
                                            <select name="status">
                                                <?php foreach ($this->repo->application_statuses() as $status) : ?>
                                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($application->status, $status); ?>><?php echo esc_html($status); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <textarea name="decline_reason_private" placeholder="<?php esc_attr_e('Private decline/review note', 'vms-sponsorships'); ?>" rows="2"><?php echo esc_textarea($application->decline_reason_private); ?></textarea>
                                            <button class="button button-primary" type="submit"><?php esc_html_e('Update', 'vms-sponsorships'); ?></button>
                                        </form>

                                        <div class="vms-sponsorships-application-action-links">
                                            <?php if ($linked_assignment) : ?>
                                                <a class="button button-secondary" href="<?php echo esc_url($assignment_url); ?>"><?php esc_html_e('View Assignment', 'vms-sponsorships'); ?></a>
                                            <?php else : ?>
                                                <a class="button button-secondary" href="<?php echo esc_url($this->assignment_create_from_application_url($application->id, false)); ?>"><?php esc_html_e('Create Assignment', 'vms-sponsorships'); ?></a>
                                                <?php if ($this->application_can_be_approved_on_create($application)) : ?>
                                                    <a class="button button-secondary" href="<?php echo esc_url($this->assignment_create_from_application_url($application->id, true)); ?>"><?php esc_html_e('Approve & Create Assignment', 'vms-sponsorships'); ?></a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_assignments() {
        $assignments = $this->repo->get_assignments(array('limit' => 100));
        $packages = $this->repo->get_packages(false);
        $package_lookup = array();
        foreach ($packages as $package) {
            $package_lookup[absint($package->id)] = $package;
        }

        $requested_assignment_id = !empty($_GET['assignment_id']) ? absint(wp_unslash($_GET['assignment_id'])) : 0;
        $requested_application_id = !empty($_GET['application_id']) ? absint(wp_unslash($_GET['application_id'])) : 0;
        $requested_event_id = !empty($_GET['event_id']) ? absint(wp_unslash($_GET['event_id'])) : 0;
        $approve_after_create = !empty($_GET['approve_application']);

        $edit = $requested_assignment_id ? $this->repo->get_assignment($requested_assignment_id) : null;
        $source_application = $requested_application_id ? $this->repo->get_application($requested_application_id) : null;
        $linked_assignment = $requested_application_id ? $this->repo->get_assignment_by_application_id($requested_application_id) : null;

        if (!$edit && $linked_assignment) {
            $edit = $linked_assignment;
        }

        if (!$source_application && $edit && !empty($edit->application_id)) {
            $source_application = $this->repo->get_application($edit->application_id);
        }

        $requested_package_id = $source_application ? absint($source_application->requested_package_id) : 0;
        $requested_package = $requested_package_id > 0 ? ($package_lookup[$requested_package_id] ?? $this->repo->get_package($requested_package_id)) : null;
        $default_amount = $requested_package ? (float) $requested_package->base_price : 0.0;
        $default_payment_status = $this->default_payment_status_for_application($source_application);
        $default_in_kind_value = $this->default_in_kind_value_for_application($source_application);
        $default_assignment_scope = $edit
            ? $this->assignment_scope_value($edit)
            : ($source_application && sanitize_key((string) ($source_application->scope ?? '')) === 'season' ? 'date_range' : 'event');
        $default_asset_status = $edit
            ? $this->normalize_assignment_asset_status((string) $edit->asset_status, absint($edit->id), absint($edit->application_id))
            : $this->default_assignment_asset_status($source_application, $requested_package);
        $default_fulfillment_status = $edit
            ? $this->normalize_fulfillment_status((string) $edit->fulfillment_status)
            : $this->default_assignment_fulfillment_status($source_application, $requested_package);
        $default_notes = $source_application ? $this->application_assignment_notes($source_application) : '';
        $default_physical_banner = $requested_package && !empty($requested_package->includes_physical_banner) ? 1 : 0;
        $default_slot = $source_application && !empty($source_application->requested_slot_key) ? $source_application->requested_slot_key : 'presenting';

        $form_values = array(
            'id' => $edit ? absint($edit->id) : 0,
            'vendor_id' => $edit ? absint($edit->vendor_id) : absint($source_application->vendor_id ?? 0),
            'user_id' => $edit ? absint($edit->user_id) : absint($source_application->user_id ?? 0),
            'application_id' => $edit ? absint($edit->application_id) : absint($source_application->id ?? 0),
            'assignment_scope' => $default_assignment_scope,
            'event_id' => $edit ? absint($edit->event_id) : ($requested_event_id ?: absint($source_application->event_id ?? 0)),
            'season_id' => $edit ? absint($edit->season_id) : absint($source_application->season_id ?? 0),
            'scope_label' => $edit ? (string) $edit->scope_label : '',
            'starts_at' => $edit ? (string) $edit->starts_at : '',
            'ends_at' => $edit ? (string) $edit->ends_at : '',
            'package_id' => $edit ? absint($edit->package_id) : $requested_package_id,
            'slot_key' => $edit ? (string) $edit->slot_key : $default_slot,
            'status' => $edit ? (string) $edit->status : 'prospect',
            'sponsor_display_name' => $edit ? (string) $edit->sponsor_display_name : (string) ($source_application->business_name ?? ''),
            'sponsor_tagline' => $edit ? (string) $edit->sponsor_tagline : '',
            'sponsor_url' => $edit ? (string) $edit->sponsor_url : (string) ($source_application->website_url ?? ''),
            'amount' => $edit ? (string) $edit->amount : number_format($default_amount, 2, '.', ''),
            'in_kind_value' => $edit ? (string) $edit->in_kind_value : number_format($default_in_kind_value, 2, '.', ''),
            'payment_status' => $edit ? (string) $edit->payment_status : $default_payment_status,
            'public_display_enabled' => $edit ? (int) $edit->public_display_enabled : 1,
            'placeholder_enabled' => $edit ? (int) $edit->placeholder_enabled : 1,
            'physical_banner_included' => $edit ? (int) $edit->physical_banner_included : $default_physical_banner,
            'asset_status' => $default_asset_status,
            'fulfillment_status' => $default_fulfillment_status,
            'internal_notes' => $edit ? (string) $edit->internal_notes : $default_notes,
        );

        $event_options = $this->assignment_event_options($form_values['event_id']);
        $can_approve_on_create = !$edit && $source_application && $this->application_can_be_approved_on_create($source_application);
        $button_label = $edit
            ? __('Save Assignment', 'vms-sponsorships')
            : ($can_approve_on_create && $approve_after_create
                ? __('Approve & Create Assignment', 'vms-sponsorships')
                : __('Create Assignment', 'vms-sponsorships'));
        ?>
        <div class="wrap">
            <?php $this->render_page_header(__('Sponsorship Assignments', 'vms-sponsorships'), __('Manage sponsor placements, payment state, asset readiness, and fulfillment follow-through.', 'vms-sponsorships')); ?>

            <style>
                .vms-sponsorships-source-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-left: 4px solid #2271b1;
                    margin: 0 0 16px;
                    max-width: 900px;
                    padding: 16px;
                }
                .vms-sponsorships-source-card p {
                    margin: 0 0 8px;
                }
                .vms-sponsorships-source-card p:last-child {
                    margin-bottom: 0;
                }
                .vms-sponsorships-source-card__actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 12px;
                }
                .vms-sponsorships-event-picker {
                    display: grid;
                    gap: 8px;
                    max-width: 560px;
                }
                .vms-sponsorships-event-picker select {
                    max-width: 100%;
                    min-height: 220px;
                }
                .vms-sponsorships-event-picker__feedback {
                    min-height: 20px;
                }
                .vms-sponsorships-form-section td {
                    border-top: 1px solid #dcdcde;
                    padding-top: 18px;
                }
                .vms-sponsorships-form-section h3 {
                    margin: 0 0 4px;
                }
                .vms-sponsorships-scope-choices {
                    display: grid;
                    gap: 8px;
                }
                .vms-sponsorships-scope-choices label {
                    align-items: center;
                    display: flex;
                    gap: 8px;
                }
            </style>

            <?php if ($requested_application_id && !$source_application) : ?>
                <div class="notice notice-warning inline" style="max-width:900px;">
                    <p><?php esc_html_e('The requested source application could not be loaded. You can still create an assignment manually.', 'vms-sponsorships'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($source_application) : ?>
                <div class="vms-sponsorships-source-card">
                    <p>
                        <strong><?php esc_html_e('Source application', 'vms-sponsorships'); ?>:</strong>
                        <?php echo esc_html($source_application->business_name); ?>
                        <span style="margin-left:8px;"><?php echo $this->status_pill_html($source_application->status); ?></span>
                    </p>
                    <p>
                        <?php
                        echo esc_html(
                            implode(
                                ' · ',
                                array_filter(array(
                                    $source_application->contact_name ?: '',
                                    $source_application->email ?: '',
                                    $source_application->phone ?: '',
                                ))
                            )
                        );
                        ?>
                    </p>
                    <p>
                        <?php
                        $source_context = array(
                            $this->event_label($source_application->event_id),
                            $this->scope_label($source_application->scope ?? 'event'),
                        );
                        if (!empty($source_application->requested_slot_key)) {
                            $source_context[] = $this->slot_label($source_application->requested_slot_key);
                        }
                        if ($requested_package) {
                            $source_context[] = $requested_package->name;
                        }
                        echo esc_html(implode(' · ', array_filter($source_context)));
                        ?>
                    </p>
                    <?php if (!empty($source_application->website_url)) : ?>
                        <p><a href="<?php echo esc_url($source_application->website_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($source_application->website_url); ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($source_application->sponsor_message)) : ?>
                        <p><strong><?php esc_html_e('Application message:', 'vms-sponsorships'); ?></strong> <?php echo esc_html($source_application->sponsor_message); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($source_application->trade_offer_details)) : ?>
                        <p><strong><?php esc_html_e('Trade details:', 'vms-sponsorships'); ?></strong> <?php echo esc_html($source_application->trade_offer_details); ?></p>
                    <?php endif; ?>
                    <?php if ($linked_assignment && !$requested_assignment_id) : ?>
                        <p><?php esc_html_e('An assignment has already been created from this application. Duplicate creation is blocked, so the linked assignment is shown below instead.', 'vms-sponsorships'); ?></p>
                    <?php elseif ($can_approve_on_create && $approve_after_create) : ?>
                        <p><?php esc_html_e('Saving this form will create the assignment first and only then mark the application approved.', 'vms-sponsorships'); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e('Fields below were prefilled from the application. Creating the assignment will not change the application status unless you explicitly use Approve & Create Assignment.', 'vms-sponsorships'); ?></p>
                    <?php endif; ?>
                    <div class="vms-sponsorships-source-card__actions">
                        <a class="button button-secondary" href="<?php echo esc_url($this->application_review_url($source_application->id)); ?>"><?php esc_html_e('Open Application Review', 'vms-sponsorships'); ?></a>
                        <?php if ($linked_assignment) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url($this->assignment_edit_url($linked_assignment->id)); ?>"><?php esc_html_e('View Assignment', 'vms-sponsorships'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h2><?php echo $edit ? esc_html__('Edit Assignment', 'vms-sponsorships') : ($source_application ? esc_html__('Create Assignment from Application', 'vms-sponsorships') : esc_html__('Create Assignment', 'vms-sponsorships')); ?></h2>
            <form method="post" style="max-width:900px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
                <?php wp_nonce_field('vms_sponsorships_save_assignment'); ?>
                <input type="hidden" name="vms_sponsorships_action" value="save_assignment">
                <input type="hidden" name="id" value="<?php echo esc_attr($form_values['id']); ?>">
                <input type="hidden" name="vendor_id" value="<?php echo esc_attr($form_values['vendor_id']); ?>">
                <input type="hidden" name="user_id" value="<?php echo esc_attr($form_values['user_id']); ?>">
                <input type="hidden" name="application_id" value="<?php echo esc_attr($form_values['application_id']); ?>">
                <input type="hidden" name="season_id" value="<?php echo esc_attr($form_values['season_id']); ?>">
                <?php if ($can_approve_on_create && $approve_after_create) : ?>
                    <input type="hidden" name="approve_application_after_create" value="1">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <?php if ($form_values['application_id']) : ?>
                        <tr>
                            <th><?php esc_html_e('Linked application', 'vms-sponsorships'); ?></th>
                            <td><a href="<?php echo esc_url($this->application_review_url($form_values['application_id'])); ?>"><?php echo esc_html(sprintf(__('Application #%d', 'vms-sponsorships'), $form_values['application_id'])); ?></a></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="sponsor_display_name"><?php esc_html_e('Sponsor display name', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_display_name" name="sponsor_display_name" value="<?php echo esc_attr($form_values['sponsor_display_name']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Scope', 'vms-sponsorships'); ?></th>
                        <td>
                            <div class="vms-sponsorships-scope-choices">
                                <label for="assignment_scope_event">
                                    <input type="radio" id="assignment_scope_event" name="assignment_scope" value="event" <?php checked($form_values['assignment_scope'], 'event'); ?>>
                                    <?php esc_html_e('Single event', 'vms-sponsorships'); ?>
                                </label>
                                <label for="assignment_scope_date_range">
                                    <input type="radio" id="assignment_scope_date_range" name="assignment_scope" value="date_range" <?php checked($form_values['assignment_scope'], 'date_range'); ?>>
                                    <?php esc_html_e('Season / date range', 'vms-sponsorships'); ?>
                                </label>
                            </div>
                            <p class="description"><?php esc_html_e('Single-event assignments link directly to one event. Season/date-range assignments apply across matching published events whose start date falls within the selected date range, while cancelled and non-public events are skipped for public banner matching.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr id="vms-sponsorships-assignment-event-row">
                        <th><label for="event_id"><?php esc_html_e('Event', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <div class="vms-sponsorships-event-picker">
                                <input type="search" id="vms-sponsorships-event-search" placeholder="<?php esc_attr_e('Search events by title, date, type, or ID', 'vms-sponsorships'); ?>">
                                <select id="event_id" name="event_id" size="8" aria-describedby="vms-sponsorships-event-search-feedback vms-sponsorships-event-help">
                                    <option value=""><?php esc_html_e('No linked event', 'vms-sponsorships'); ?></option>
                                    <?php foreach ($event_options as $option) : ?>
                                        <option value="<?php echo esc_attr($option['id']); ?>" data-search="<?php echo esc_attr($option['search']); ?>" <?php selected($form_values['event_id'], $option['id']); ?>><?php echo esc_html($option['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description vms-sponsorships-event-picker__feedback" id="vms-sponsorships-event-search-feedback"><?php esc_html_e('Start typing to filter the event list. Your current selection stays linked until you choose a different event or No linked event.', 'vms-sponsorships'); ?></p>
                            </div>
                            <p class="description" id="vms-sponsorships-event-help"><?php esc_html_e('Choose a linked event or event plan without typing a raw ID. If the application was event-specific, it is preselected here.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr id="vms-sponsorships-assignment-scope-label-row">
                        <th><label for="scope_label"><?php esc_html_e('Season label', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <input class="regular-text" id="scope_label" name="scope_label" value="<?php echo esc_attr($form_values['scope_label']); ?>" placeholder="<?php esc_attr_e('Example: Fall 2026 Season', 'vms-sponsorships'); ?>">
                            <p class="description"><?php esc_html_e('Optional. Use this when the sponsor package should be identified with a public-facing season label instead of only raw dates.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr id="vms-sponsorships-assignment-start-row">
                        <th><label for="starts_at"><?php esc_html_e('Start date', 'vms-sponsorships'); ?></label></th>
                        <td><input type="date" id="starts_at" name="starts_at" value="<?php echo esc_attr($this->date_input_value($form_values['starts_at'])); ?>"></td>
                    </tr>
                    <tr id="vms-sponsorships-assignment-end-row">
                        <th><label for="ends_at"><?php esc_html_e('End date', 'vms-sponsorships'); ?></label></th>
                        <td><input type="date" id="ends_at" name="ends_at" value="<?php echo esc_attr($this->date_input_value($form_values['ends_at'])); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="package_id"><?php esc_html_e('Package', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="package_id" name="package_id">
                                <option value=""><?php esc_html_e('No package', 'vms-sponsorships'); ?></option>
                                <?php foreach ($packages as $package) : ?>
                                    <option value="<?php echo esc_attr($package->id); ?>" <?php selected($form_values['package_id'], $package->id); ?>><?php echo esc_html($package->name . ' — $' . number_format_i18n((float) $package->base_price, 2) . (!empty($package->active) ? '' : ' [' . __('Inactive', 'vms-sponsorships') . ']')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="slot_key"><?php esc_html_e('Slot', 'vms-sponsorships'); ?></label></th>
                        <td><input id="slot_key" name="slot_key" value="<?php echo esc_attr($form_values['slot_key']); ?>"> <p class="description">presenting, bar, veterans, kids, food_truck, supporting</p></td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php esc_html_e('Status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <?php foreach ($this->repo->assignment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($form_values['status'], $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="payment_status"><?php esc_html_e('Payment status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="payment_status" name="payment_status">
                                <?php foreach ($this->repo->payment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($form_values['payment_status'], $status); ?>><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amount"><?php esc_html_e('Amount', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" step="0.01" id="amount" name="amount" value="<?php echo esc_attr($form_values['amount']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="in_kind_value"><?php esc_html_e('In-kind value', 'vms-sponsorships'); ?></label></th>
                        <td><input type="number" step="0.01" id="in_kind_value" name="in_kind_value" value="<?php echo esc_attr($form_values['in_kind_value']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="sponsor_url"><?php esc_html_e('Sponsor URL', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_url" name="sponsor_url" value="<?php echo esc_attr($form_values['sponsor_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="sponsor_tagline"><?php esc_html_e('Tagline', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="sponsor_tagline" name="sponsor_tagline" value="<?php echo esc_attr($form_values['sponsor_tagline']); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Display controls', 'vms-sponsorships'); ?></th>
                        <td>
                            <label><input type="checkbox" name="public_display_enabled" value="1" <?php checked($form_values['public_display_enabled'], 1); ?>> <?php esc_html_e('Public display enabled', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="placeholder_enabled" value="1" <?php checked($form_values['placeholder_enabled'], 1); ?>> <?php esc_html_e('Placeholder enabled if slot is open', 'vms-sponsorships'); ?></label><br>
                            <label><input type="checkbox" name="physical_banner_included" value="1" <?php checked($form_values['physical_banner_included'], 1); ?>> <?php esc_html_e('Includes physical on-site banner', 'vms-sponsorships'); ?></label>
                        </td>
                    </tr>
                    <tr class="vms-sponsorships-form-section">
                        <td colspan="2">
                            <h3><?php esc_html_e('Review & fulfillment tracking', 'vms-sponsorships'); ?></h3>
                            <p class="description"><?php esc_html_e('These tracking fields can usually stay at their defaults during initial assignment creation and be updated later as artwork is reviewed and sponsor obligations are delivered.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="asset_status"><?php esc_html_e('Asset status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="asset_status" name="asset_status">
                                <?php foreach ($this->repo->assignment_asset_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($form_values['asset_status'], $status); ?>><?php echo esc_html($this->assignment_asset_status_label($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Use this when sponsor artwork or logos have been submitted for review.', 'vms-sponsorships'); ?></p>
                            <p class="description"><?php esc_html_e('Leave this at the default during initial assignment creation unless files have already been uploaded.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fulfillment_status"><?php esc_html_e('Fulfillment status', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <select id="fulfillment_status" name="fulfillment_status">
                                <?php foreach ($this->repo->fulfillment_statuses() as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($form_values['fulfillment_status'], $status); ?>><?php echo esc_html($this->fulfillment_status_label($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Usually updated later after placement, signage, event-page display, or sponsor reporting is complete.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="internal_notes"><?php esc_html_e('Internal notes', 'vms-sponsorships'); ?></label></th>
                        <td><textarea class="large-text" rows="6" id="internal_notes" name="internal_notes"><?php echo esc_textarea($form_values['internal_notes']); ?></textarea></td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit"><?php echo esc_html($button_label); ?></button></p>
            </form>

            <script>
                (function () {
                    var searchInput = document.getElementById('vms-sponsorships-event-search');
                    var select = document.getElementById('event_id');
                    var feedback = document.getElementById('vms-sponsorships-event-search-feedback');
                    var scopeInputs = document.querySelectorAll('input[name="assignment_scope"]');
                    var eventRow = document.getElementById('vms-sponsorships-assignment-event-row');
                    var scopeLabelRow = document.getElementById('vms-sponsorships-assignment-scope-label-row');
                    var startRow = document.getElementById('vms-sponsorships-assignment-start-row');
                    var endRow = document.getElementById('vms-sponsorships-assignment-end-row');
                    var scopeLabelInput = document.getElementById('scope_label');
                    var startsAtInput = document.getElementById('starts_at');
                    var endsAtInput = document.getElementById('ends_at');
                    if (!searchInput || !select || !feedback) {
                        return;
                    }

                    var baseOptions = Array.prototype.slice.call(select.options).map(function (option) {
                        return {
                            value: option.value,
                            text: option.text,
                            search: (option.getAttribute('data-search') || option.text || '').toLowerCase(),
                            disabled: !!option.disabled
                        };
                    });
                    var noLinkedOption = baseOptions.shift() || {
                        value: '',
                        text: '<?php echo esc_js(__('No linked event', 'vms-sponsorships')); ?>',
                        search: 'no linked event',
                        disabled: false
                    };
                    var eventOptions = baseOptions;
                    var currentValue = select.value;
                    var noResultsLabel = '<?php echo esc_js(__('No matching events found', 'vms-sponsorships')); ?>';
                    var currentSelectionSuffix = '<?php echo esc_js(__('Currently selected', 'vms-sponsorships')); ?>';
                    var baseFeedback = '<?php echo esc_js(__('Start typing to filter the event list. Your current selection stays linked until you choose a different event or No linked event.', 'vms-sponsorships')); ?>';
                    var showingLabel = '<?php echo esc_js(__('Showing', 'vms-sponsorships')); ?>';
                    var matchingEventSingularLabel = '<?php echo esc_js(__('matching event', 'vms-sponsorships')); ?>';
                    var matchingEventPluralLabel = '<?php echo esc_js(__('matching events', 'vms-sponsorships')); ?>';
                    var keptSelectionLabel = '<?php echo esc_js(__('The current selection is still visible below.', 'vms-sponsorships')); ?>';

                    function selectedScope() {
                        var selected = 'event';

                        scopeInputs.forEach(function (input) {
                            if (input.checked) {
                                selected = input.value;
                            }
                        });

                        return selected;
                    }

                    function toggleScopeRows() {
                        var isDateRange = selectedScope() === 'date_range';

                        if (eventRow) {
                            eventRow.style.display = isDateRange ? 'none' : '';
                        }
                        searchInput.disabled = isDateRange;
                        select.disabled = isDateRange;

                        [scopeLabelRow, startRow, endRow].forEach(function (row) {
                            if (row) {
                                row.style.display = isDateRange ? '' : 'none';
                            }
                        });

                        if (scopeLabelInput) {
                            scopeLabelInput.disabled = !isDateRange;
                        }
                        if (startsAtInput) {
                            startsAtInput.disabled = !isDateRange;
                            startsAtInput.required = isDateRange;
                        }
                        if (endsAtInput) {
                            endsAtInput.disabled = !isDateRange;
                            endsAtInput.required = isDateRange;
                        }
                    }

                    function buildOption(definition, extraLabel) {
                        var option = document.createElement('option');
                        option.value = definition.value;
                        option.text = extraLabel ? definition.text + ' \u2014 ' + extraLabel : definition.text;
                        option.disabled = !!definition.disabled;
                        if (definition.search) {
                            option.setAttribute('data-search', definition.search);
                        }
                        return option;
                    }

                    function updateFeedback(query, matchCount, keptSelectionVisible) {
                        if (query === '') {
                            feedback.textContent = eventOptions.length
                                ? baseFeedback
                                : '<?php echo esc_js(__('No events are currently available. You can leave this as No linked event.', 'vms-sponsorships')); ?>';
                            return;
                        }

                        if (matchCount > 0) {
                            feedback.textContent = showingLabel + ' ' + matchCount + ' '
                                + (matchCount === 1 ? matchingEventSingularLabel : matchingEventPluralLabel) + '.'
                                + (keptSelectionVisible ? ' ' + keptSelectionLabel : '');
                            return;
                        }

                        feedback.textContent = '<?php echo esc_js(__('No matching events found. Clear the search or choose No linked event.', 'vms-sponsorships')); ?>'
                            + (keptSelectionVisible ? ' ' + keptSelectionLabel : '');
                    }

                    function renderOptions() {
                        var query = searchInput.value.toLowerCase().trim();
                        var selectedOption = null;
                        var matchingOptions = eventOptions.filter(function (option) {
                            if (option.value === currentValue) {
                                selectedOption = option;
                            }

                            return query === '' || option.search.indexOf(query) !== -1;
                        });
                        var keptSelectionVisible = !!(
                            currentValue
                            && selectedOption
                            && query !== ''
                            && matchingOptions.every(function (option) {
                                return option.value !== currentValue;
                            })
                        );

                        select.innerHTML = '';
                        select.appendChild(buildOption(noLinkedOption));

                        if (keptSelectionVisible) {
                            select.appendChild(buildOption(selectedOption, currentSelectionSuffix));
                        }

                        if (matchingOptions.length) {
                            matchingOptions.forEach(function (option) {
                                select.appendChild(buildOption(option));
                            });
                        } else if (!keptSelectionVisible) {
                            select.appendChild(buildOption({
                                value: '',
                                text: noResultsLabel,
                                search: '',
                                disabled: true
                            }));
                        }

                        var hasCurrentValue = Array.prototype.some.call(select.options, function (option) {
                            return option.value === currentValue;
                        });
                        select.value = hasCurrentValue ? currentValue : '';
                        currentValue = select.value;

                        updateFeedback(query, matchingOptions.length, keptSelectionVisible);
                    }

                    select.addEventListener('change', function () {
                        currentValue = select.value;
                        renderOptions();
                    });

                    searchInput.addEventListener('input', renderOptions);
                    searchInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === 'ArrowDown') {
                            event.preventDefault();
                            select.focus();
                        }
                    });

                    scopeInputs.forEach(function (input) {
                        input.addEventListener('change', toggleScopeRows);
                    });

                    toggleScopeRows();
                    renderOptions();
                }());
            </script>

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
                                                <option value="<?php echo esc_attr($status); ?>" <?php selected($item->status, $status); ?>><?php echo esc_html($this->fulfillment_status_label($status)); ?></option>
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
                        <th><?php esc_html_e('Scope', 'vms-sponsorships'); ?></th>
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
                            <td><?php echo esc_html($this->assignment_scope_summary($assignment)); ?></td>
                            <td><code><?php echo esc_html($assignment->slot_key); ?></code></td>
                            <td><code><?php echo esc_html($assignment->status); ?></code></td>
                            <td><code><?php echo esc_html($assignment->payment_status); ?></code></td>
                            <td><?php echo $assignment->physical_banner_included ? esc_html__('Yes', 'vms-sponsorships') : esc_html__('No', 'vms-sponsorships'); ?></td>
                            <td><?php echo esc_html('$' . number_format_i18n((float) $assignment->amount, 2)); ?></td>
                            <td><?php echo wp_kses_post($this->assignment_shortcode_summary($assignment)); ?></td>
                            <td><a class="button" href="<?php echo esc_url($this->assignment_edit_url($assignment->id)); ?>"><?php esc_html_e('Edit', 'vms-sponsorships'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_packages() {
        $packages = $this->repo->get_packages(false);
        $edit = !empty($_GET['package_id']) ? $this->repo->get_package(absint(wp_unslash($_GET['package_id']))) : null;
        ?>
        <div class="wrap">
            <?php $this->render_page_header(__('Sponsorship Packages', 'vms-sponsorships'), __('Set pricing, package scope, banner rules, required assets, and fulfillment templates.', 'vms-sponsorships')); ?>

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
            <?php $this->render_page_header(__('Sponsor Asset Review', 'vms-sponsorships'), __('Approve sponsor creative and keep revisions moving before placements go live.', 'vms-sponsorships')); ?>
            <?php $this->render_focus_table_styles(); ?>
            <div class="vms-sponsorships-focus-table-wrap">
                <table class="widefat striped vms-sponsorships-focus-table">
                    <thead><tr><th><?php esc_html_e('Preview', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Type', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Assignment', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Status', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Uploaded', 'vms-sponsorships'); ?></th><th><?php esc_html_e('Action', 'vms-sponsorships'); ?></th></tr></thead>
                    <tbody>
                        <?php if (empty($assets)) : ?><tr><td colspan="6"><?php esc_html_e('No sponsor assets uploaded yet.', 'vms-sponsorships'); ?></td></tr><?php endif; ?>
                        <?php foreach ($assets as $asset) : ?>
                            <?php
                            $attachment_id = (int) $asset->attachment_id;
                            $attachment_url = $attachment_id > 0 ? wp_get_attachment_url($attachment_id) : '';
                            $attachment_label = '';
                            if ($attachment_id > 0 && function_exists('get_attached_file')) {
                                $attachment_label = wp_basename((string) get_attached_file($attachment_id));
                            }
                            if ($attachment_label === '') {
                                $attachment_label = sprintf(__('Attachment #%d', 'vms-sponsorships'), $attachment_id);
                            }
                            ?>
                            <tr id="<?php echo esc_attr($this->asset_anchor_id($asset->id)); ?>">
                                <td>
                                    <?php echo wp_get_attachment_image($attachment_id, array(120, 80)); ?>
                                    <div class="description" style="margin-top:8px;">
                                        <strong><?php echo esc_html($attachment_label); ?></strong>
                                        <?php if ($attachment_url) : ?>
                                            <br><a href="<?php echo esc_url($attachment_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open file', 'vms-sponsorships'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
        </div>
        <?php
    }

    public function render_visibility_stats() {
        $visibility_defaults = VMS_Sponsorships_Install::default_visibility_settings();
        $visibility = $this->visibility_snapshot();
        $has_saved_reference = !empty($visibility['last_saved_at']) || !empty($visibility['public_last_updated_timestamp']);
        $visibility_website_value = (string) get_option('vms_sponsorships_visibility_website', $visibility_defaults['vms_sponsorships_visibility_website']);
        $visibility_attendance_value = (string) get_option('vms_sponsorships_visibility_attendance', $visibility_defaults['vms_sponsorships_visibility_attendance']);

        if (!$has_saved_reference) {
            if ($visibility_website_value === $visibility_defaults['vms_sponsorships_visibility_website']) {
                $visibility_website_value = '';
            }

            if ($visibility_attendance_value === $visibility_defaults['vms_sponsorships_visibility_attendance']) {
                $visibility_attendance_value = '';
            }
        }
        ?>
        <div class="wrap">
            <?php $this->render_page_header(__('Visibility Stats', 'vms-sponsorships'), __('Maintain the sponsor-facing audience and visibility proof points shown on sponsorship inquiry pages and proposal workflows.', 'vms-sponsorships')); ?>
            <div style="max-width:960px;">
                <div style="background:#fff;border:1px solid #ccd0d4;padding:16px 18px;margin-bottom:16px;">
                    <p style="margin-top:0;"><strong><?php esc_html_e('Manual sponsor proof points only.', 'vms-sponsorships'); ?></strong> <?php esc_html_e('These values are manually maintained and should not imply real-time API stats.', 'vms-sponsorships'); ?></p>
                    <p><?php echo $this->state_pill_html($visibility['label'], $visibility['tone']); ?></p>
                    <p class="description" style="margin-bottom:8px;"><?php echo esc_html($visibility['detail']); ?></p>
                    <p class="description" style="margin-bottom:0;"><?php esc_html_e('Update monthly or before sending sponsor proposals.', 'vms-sponsorships'); ?></p>
                </div>

                <form method="post" style="background:#fff;padding:16px;border:1px solid #ccd0d4;">
                    <?php wp_nonce_field('vms_sponsorships_save_visibility_stats'); ?>
                    <input type="hidden" name="vms_sponsorships_action" value="save_visibility_stats">

                    <h2 style="margin-top:0;"><?php esc_html_e('Public Visibility Stats', 'vms-sponsorships'); ?></h2>
                    <p><?php esc_html_e('These proof points appear on sponsor-facing inquiry pages. Keep them grounded, current, and framed as audience visibility rather than guaranteed results.', 'vms-sponsorships'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="visibility_title"><?php esc_html_e('Section title', 'vms-sponsorships'); ?></label></th>
                            <td>
                                <input class="regular-text" id="visibility_title" name="visibility_title" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_title', $visibility_defaults['vms_sponsorships_visibility_title'])); ?>">
                                <p class="description"><?php esc_html_e('Public heading shown above the visibility proof points.', 'vms-sponsorships'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="visibility_intro"><?php esc_html_e('Short intro', 'vms-sponsorships'); ?></label></th>
                            <td>
                                <textarea class="large-text" rows="3" id="visibility_intro" name="visibility_intro"><?php echo esc_textarea(get_option('vms_sponsorships_visibility_intro', $visibility_defaults['vms_sponsorships_visibility_intro'])); ?></textarea>
                                <p class="description"><?php esc_html_e('Use brief framing copy only. The proof points below should carry the substance.', 'vms-sponsorships'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="visibility_facebook"><?php esc_html_e('Facebook followers / audience value', 'vms-sponsorships'); ?></label></th>
                            <td><input class="regular-text" id="visibility_facebook" name="visibility_facebook" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_facebook', $visibility_defaults['vms_sponsorships_visibility_facebook'])); ?>" placeholder="<?php esc_attr_e('Example: 4,800 followers', 'vms-sponsorships'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_instagram"><?php esc_html_e('Instagram followers / audience value', 'vms-sponsorships'); ?></label></th>
                            <td><input class="regular-text" id="visibility_instagram" name="visibility_instagram" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_instagram', $visibility_defaults['vms_sponsorships_visibility_instagram'])); ?>" placeholder="<?php esc_attr_e('Example: 3,200 followers', 'vms-sponsorships'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_email"><?php esc_html_e('Email subscribers / list size', 'vms-sponsorships'); ?></label></th>
                            <td><input class="regular-text" id="visibility_email" name="visibility_email" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_email', $visibility_defaults['vms_sponsorships_visibility_email'])); ?>" placeholder="<?php esc_attr_e('Example: 1,900 subscribers', 'vms-sponsorships'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_website"><?php esc_html_e('Website / event-page audience note', 'vms-sponsorships'); ?></label></th>
                            <td><textarea class="large-text" rows="2" id="visibility_website" name="visibility_website" placeholder="<?php esc_attr_e('Example: Featured event pages and sponsor landing pages across the current calendar.', 'vms-sponsorships'); ?>"><?php echo esc_textarea($visibility_website_value); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_attendance"><?php esc_html_e('Typical attendance / seasonal audience note', 'vms-sponsorships'); ?></label></th>
                            <td><textarea class="large-text" rows="2" id="visibility_attendance" name="visibility_attendance" placeholder="<?php esc_attr_e('Example: Seasonal events regularly draw East Texas live music fans, families, and community supporters.', 'vms-sponsorships'); ?>"><?php echo esc_textarea($visibility_attendance_value); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_custom_note"><?php esc_html_e('Custom sponsor visibility note', 'vms-sponsorships'); ?></label></th>
                            <td><textarea class="large-text" rows="2" id="visibility_custom_note" name="visibility_custom_note" placeholder="<?php esc_attr_e('Example: Sponsorship packages can include venue signage, newsletter mentions, and social callouts depending on placement.', 'vms-sponsorships'); ?>"><?php echo esc_textarea(get_option('vms_sponsorships_visibility_custom_note', $visibility_defaults['vms_sponsorships_visibility_custom_note'])); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="visibility_last_updated"><?php esc_html_e('Public "As of" date', 'vms-sponsorships'); ?></label></th>
                            <td>
                                <input type="date" id="visibility_last_updated" name="visibility_last_updated" value="<?php echo esc_attr(get_option('vms_sponsorships_visibility_last_updated', $visibility_defaults['vms_sponsorships_visibility_last_updated'])); ?>">
                                <p class="description"><?php esc_html_e('Shown publicly as the date these proof points were last updated.', 'vms-sponsorships'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Internal Notes', 'vms-sponsorships'); ?></h2>
                    <p><?php esc_html_e('Private operator notes stay in admin only. Use this for source context, reminders, or proposal prep notes.', 'vms-sponsorships'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="visibility_internal_notes"><?php esc_html_e('Optional internal notes', 'vms-sponsorships'); ?></label></th>
                            <td><textarea class="large-text" rows="4" id="visibility_internal_notes" name="visibility_internal_notes"><?php echo esc_textarea(get_option('vms_sponsorships_visibility_internal_notes', $visibility_defaults['vms_sponsorships_visibility_internal_notes'])); ?></textarea></td>
                        </tr>
                    </table>

                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Visibility Stats', 'vms-sponsorships'); ?></button></p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        $banner_defaults = VMS_Sponsorships_Install::default_unsold_banner_settings();
        $legacy_banner_image_id = absint(get_option('vms_sponsorships_banner_image_id', $banner_defaults['vms_sponsorships_banner_image_id']));
        $desktop_banner_image = $this->banner_image_field_state('vms_sponsorships_banner_desktop_image_id', array(
            'legacy_fallback' => $legacy_banner_image_id,
            'empty_summary' => __('No desktop image selected.', 'vms-sponsorships'),
        ));
        $mobile_banner_image = $this->banner_image_field_state('vms_sponsorships_banner_mobile_image_id', array(
            'empty_summary' => __('No mobile image selected. The desktop image will be reused on narrow screens.', 'vms-sponsorships'),
        ));
        $show_mobile_readability_warning = !empty($desktop_banner_image['is_wide']) && absint($mobile_banner_image['image_id']) <= 0;
        $event_page_mode = $this->sanitize_event_page_placement_mode(get_option('vms_sponsorships_event_page_placement_mode', $banner_defaults['vms_sponsorships_event_page_placement_mode']));
        ?>
        <div class="wrap">
            <?php $this->render_page_header(__('Sponsorship Settings', 'vms-sponsorships'), __('Update banner limits, inquiry defaults, and public sponsorship workflow settings.', 'vms-sponsorships')); ?>
            <div class="notice notice-info inline" style="max-width:900px;">
                <p>
                    <?php esc_html_e('Visibility proof points now have their own workflow.', 'vms-sponsorships'); ?>
                    <a href="<?php echo esc_url($this->page_url('vms-sponsorships-visibility')); ?>"><?php esc_html_e('Open Visibility Stats', 'vms-sponsorships'); ?></a>
                </p>
            </div>
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
                <h2><?php esc_html_e('Default Unsold Event Banner', 'vms-sponsorships'); ?></h2>
                <p><?php echo wp_kses_post(__('This controls the intentional ad-style banner shown for unsold event-page sponsorship inventory. Automatic placement currently uses the The Events Calendar single-event hook when that template hook is present. Manual placement can use <code>[vms_sponsor_banner]</code> or <code>[vms_sponsor_event layout="banner"]</code>.', 'vms-sponsorships')); ?></p>
                <p class="description"><?php esc_html_e('The artwork below is used for the default unsold sponsor/ad inventory banner. Desktop artwork should usually be wide or horizontal. Mobile artwork should usually be square, 4:5, or vertical. If the desktop image contains text, a separate mobile image is strongly recommended. If no mobile image is provided, the desktop image will be reused, but text-heavy wide banners may be hard to read on phones. Avoid tiny text in either image.', 'vms-sponsorships'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="banner_headline"><?php esc_html_e('Default banner headline', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <input class="regular-text" id="banner_headline" name="banner_headline" value="<?php echo esc_attr(get_option('vms_sponsorships_banner_headline', $banner_defaults['vms_sponsorships_banner_headline'])); ?>">
                            <p class="description"><?php esc_html_e('Used when the event-page sponsor banner placement is still unsold.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="banner_body"><?php esc_html_e('Default banner body text', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <textarea class="large-text" rows="4" id="banner_body" name="banner_body"><?php echo esc_textarea(get_option('vms_sponsorships_banner_body', $banner_defaults['vms_sponsorships_banner_body'])); ?></textarea>
                            <p class="description"><?php esc_html_e('Keep this concise. Unsafe HTML is not allowed here.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="banner_cta_text"><?php esc_html_e('Default CTA text', 'vms-sponsorships'); ?></label></th>
                        <td><input class="regular-text" id="banner_cta_text" name="banner_cta_text" value="<?php echo esc_attr(get_option('vms_sponsorships_banner_cta_text', $banner_defaults['vms_sponsorships_banner_cta_text'])); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="banner_cta_url"><?php esc_html_e('Default CTA URL', 'vms-sponsorships'); ?></label></th>
                        <td>
                            <input class="regular-text" id="banner_cta_url" name="banner_cta_url" value="<?php echo esc_attr(get_option('vms_sponsorships_banner_cta_url', $banner_defaults['vms_sponsorships_banner_cta_url'])); ?>">
                            <p class="description"><?php esc_html_e('If left blank, the banner falls back to the inquiry page URL above or the built-in sponsorship application route.', 'vms-sponsorships'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Desktop banner image', 'vms-sponsorships'); ?></th>
                        <td>
                            <?php
                            $this->render_banner_image_setting_field(array(
                                'field_id' => 'banner_desktop_image_id',
                                'field_name' => 'banner_desktop_image_id',
                                'select_button_id' => 'vms-sponsorships-banner-desktop-image-select',
                                'clear_button_id' => 'vms-sponsorships-banner-desktop-image-clear',
                                'summary_id' => 'vms-sponsorships-banner-desktop-image-summary',
                                'preview_id' => 'vms-sponsorships-banner-desktop-image-preview',
                                'image_id' => $desktop_banner_image['image_id'],
                                'summary_html' => $desktop_banner_image['summary_html'],
                                'preview_html' => $desktop_banner_image['preview_html'],
                                'description' => __('Recommended for wide or horizontal sponsor/ad artwork on desktop event pages. Wide creatives render as a larger banner area instead of the smaller supporting-art frame.', 'vms-sponsorships'),
                                'descriptor' => $desktop_banner_image['descriptor'],
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Mobile banner image (optional)', 'vms-sponsorships'); ?></th>
                        <td>
                            <?php
                            $this->render_banner_image_setting_field(array(
                                'field_id' => 'banner_mobile_image_id',
                                'field_name' => 'banner_mobile_image_id',
                                'select_button_id' => 'vms-sponsorships-banner-mobile-image-select',
                                'clear_button_id' => 'vms-sponsorships-banner-mobile-image-clear',
                                'summary_id' => 'vms-sponsorships-banner-mobile-image-summary',
                                'preview_id' => 'vms-sponsorships-banner-mobile-image-preview',
                                'image_id' => $mobile_banner_image['image_id'],
                                'summary_html' => $mobile_banner_image['summary_html'],
                                'preview_html' => $mobile_banner_image['preview_html'],
                                'description' => __('Optional. Recommended for square, 4:5, or vertical sponsor/ad artwork on narrow screens, especially when the desktop creative contains small text.', 'vms-sponsorships'),
                                'descriptor' => $mobile_banner_image['descriptor'],
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr id="vms-sponsorships-banner-mobile-warning-row" <?php echo $show_mobile_readability_warning ? '' : 'style="display:none;"'; ?>>
                        <th><?php esc_html_e('Mobile readability warning', 'vms-sponsorships'); ?></th>
                        <td>
                            <div class="notice notice-warning inline" style="margin:0;">
                                <p id="vms-sponsorships-banner-mobile-warning-text"><?php esc_html_e('This desktop banner is wide. Add a mobile image so the artwork remains readable on phones.', 'vms-sponsorships'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Event-page placement mode', 'vms-sponsorships'); ?></th>
                        <td>
                            <?php foreach ($this->event_page_placement_mode_options() as $mode => $config) : ?>
                                <label style="display:block;margin-bottom:10px;">
                                    <input type="radio" name="event_page_placement_mode" value="<?php echo esc_attr($mode); ?>" <?php checked($event_page_mode, $mode); ?>>
                                    <strong><?php echo esc_html($config['label']); ?></strong>
                                    <span class="description" style="display:block;margin-left:24px;"><?php echo esc_html($config['description']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Settings', 'vms-sponsorships'); ?></button></p>
            </form>
        </div>
        <script>
        jQuery(function($) {
            var fieldConfigs = <?php echo wp_json_encode(array(
                array(
                    'input' => '#banner_desktop_image_id',
                    'select' => '#vms-sponsorships-banner-desktop-image-select',
                    'clear' => '#vms-sponsorships-banner-desktop-image-clear',
                    'summary' => '#vms-sponsorships-banner-desktop-image-summary',
                    'preview' => '#vms-sponsorships-banner-desktop-image-preview',
                    'emptySummary' => __('No desktop image selected.', 'vms-sponsorships'),
                    'kind' => 'desktop',
                    'title' => __('Select desktop unsold sponsor banner image', 'vms-sponsorships'),
                ),
                array(
                    'input' => '#banner_mobile_image_id',
                    'select' => '#vms-sponsorships-banner-mobile-image-select',
                    'clear' => '#vms-sponsorships-banner-mobile-image-clear',
                    'summary' => '#vms-sponsorships-banner-mobile-image-summary',
                    'preview' => '#vms-sponsorships-banner-mobile-image-preview',
                    'emptySummary' => __('No mobile image selected. The desktop image will be reused on narrow screens.', 'vms-sponsorships'),
                    'kind' => 'mobile',
                    'title' => __('Select mobile unsold sponsor banner image', 'vms-sponsorships'),
                ),
            )); ?>;
            var selectLabel = '<?php echo esc_js(__('Select Image', 'vms-sponsorships')); ?>';
            var replaceLabel = '<?php echo esc_js(__('Select / Replace Image', 'vms-sponsorships')); ?>';
            var selectedPrefix = '<?php echo esc_js(__('Selected image:', 'vms-sponsorships')); ?>';
            var dimensionsLabel = '<?php echo esc_js(__('Dimensions:', 'vms-sponsorships')); ?>';
            var aspectRatioLabel = '<?php echo esc_js(__('Aspect ratio:', 'vms-sponsorships')); ?>';
            var classificationLabel = '<?php echo esc_js(__('Classification:', 'vms-sponsorships')); ?>';
            var layoutTypeLabel = '<?php echo esc_js(__('Detected layout type:', 'vms-sponsorships')); ?>';
            var layoutLabels = {
                wide_banner: '<?php echo esc_js(__('Wide banner', 'vms-sponsorships')); ?>',
                square_or_standard: '<?php echo esc_js(__('Square or standard', 'vms-sponsorships')); ?>',
                portrait_mobile: '<?php echo esc_js(__('Portrait / mobile', 'vms-sponsorships')); ?>'
            };
            var untitledLabel = '<?php echo esc_js(__('Untitled media item', 'vms-sponsorships')); ?>';
            var warningLayoutKey = 'wide_banner';
            var warningText = '<?php echo esc_js(__('This desktop banner is wide. Add a mobile image so the artwork remains readable on phones.', 'vms-sponsorships')); ?>';

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function attachmentDimension(attachment, key) {
                if (attachment && attachment[key]) {
                    return parseInt(attachment[key], 10) || 0;
                }

                if (attachment && attachment.sizes && attachment.sizes.full && attachment.sizes.full[key]) {
                    return parseInt(attachment.sizes.full[key], 10) || 0;
                }

                return 0;
            }

            function classifyLayout(aspectRatio) {
                if (aspectRatio >= 2) {
                    return 'wide_banner';
                }

                if (aspectRatio > 0 && aspectRatio < 0.8) {
                    return 'portrait_mobile';
                }

                return 'square_or_standard';
            }

            function descriptorFromAttachment(attachment) {
                if (!attachment || !attachment.id) {
                    return null;
                }

                var width = attachmentDimension(attachment, 'width');
                var height = attachmentDimension(attachment, 'height');
                var aspectRatio = width > 0 && height > 0 ? (width / Math.max(1, height)) : 0;
                var layoutKey = classifyLayout(aspectRatio);

                return {
                    id: attachment.id,
                    label: attachment.title || attachment.filename || untitledLabel,
                    width: width,
                    height: height,
                    aspectRatio: aspectRatio,
                    layoutKey: layoutKey,
                    layoutLabel: layoutLabels[layoutKey] || layoutKey
                };
            }

            function aspectRatioDisplay(aspectRatio) {
                if (!aspectRatio || aspectRatio <= 0) {
                    return '';
                }

                return aspectRatio.toFixed(2) + ':1';
            }

            function buildSummaryHtml(descriptor, emptySummary) {
                if (!descriptor) {
                    return escapeHtml(emptySummary);
                }

                var parts = [
                    '<strong>' + escapeHtml(selectedPrefix) + '</strong> ' + escapeHtml(descriptor.label)
                ];

                if (descriptor.width > 0 && descriptor.height > 0) {
                    parts.push('<strong>' + escapeHtml(dimensionsLabel) + '</strong> ' + escapeHtml(String(descriptor.width) + ' x ' + String(descriptor.height) + ' px'));
                }

                if (descriptor.aspectRatio > 0) {
                    parts.push('<strong>' + escapeHtml(aspectRatioLabel) + '</strong> ' + escapeHtml(aspectRatioDisplay(descriptor.aspectRatio)));
                }

                parts.push('<strong>' + escapeHtml(classificationLabel) + '</strong> ' + escapeHtml(descriptor.layoutLabel));
                parts.push('<strong>' + escapeHtml(layoutTypeLabel) + '</strong> ' + escapeHtml(descriptor.layoutKey));

                return parts.join('<br>');
            }

            function imageDescriptorFromField(config) {
                var $input = $(config.input);
                var imageId = parseInt($input.val(), 10) || 0;

                if (imageId <= 0) {
                    return null;
                }

                return {
                    id: imageId,
                    layoutKey: $input.attr('data-vms-layout-key') || '',
                    width: parseInt($input.attr('data-vms-width'), 10) || 0,
                    height: parseInt($input.attr('data-vms-height'), 10) || 0
                };
            }

            function updateMobileReadabilityWarning() {
                var desktopConfig = fieldConfigs[0];
                var mobileConfig = fieldConfigs[1];
                var desktopDescriptor = imageDescriptorFromField(desktopConfig);
                var mobileDescriptor = imageDescriptorFromField(mobileConfig);
                var showWarning = !!desktopDescriptor && desktopDescriptor.layoutKey === warningLayoutKey && !mobileDescriptor;

                $('#vms-sponsorships-banner-mobile-warning-row').toggle(showWarning);
                $('#vms-sponsorships-banner-mobile-warning-text').text(warningText);
            }

            function buildPreviewMarkup(attachment) {
                var previewUrl = '';

                if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
                    previewUrl = attachment.sizes.medium.url;
                } else if (attachment.sizes && attachment.sizes.medium_large && attachment.sizes.medium_large.url) {
                    previewUrl = attachment.sizes.medium_large.url;
                } else if (attachment.url) {
                    previewUrl = attachment.url;
                }

                if (!previewUrl) {
                    return '';
                }

                return '<img src="' + escapeHtml(previewUrl) + '" alt="" style="display:block;max-width:280px;height:auto;border-radius:12px;">';
            }

            function updateSelectionState(config, attachment) {
                var $input = $(config.input);
                var $preview = $(config.preview);
                var $summary = $(config.summary);
                var $select = $(config.select);
                var descriptor = descriptorFromAttachment(attachment);

                if (!attachment || !attachment.id) {
                    $input.val('0');
                    $input.attr('data-vms-layout-key', '');
                    $input.attr('data-vms-width', '0');
                    $input.attr('data-vms-height', '0');
                    $summary.html(escapeHtml(config.emptySummary));
                    $preview.empty();
                    $select.text(selectLabel);
                    updateMobileReadabilityWarning();
                    return;
                }

                $input.val(String(attachment.id));
                $input.attr('data-vms-layout-key', descriptor.layoutKey);
                $input.attr('data-vms-width', String(descriptor.width || 0));
                $input.attr('data-vms-height', String(descriptor.height || 0));
                $summary.html(buildSummaryHtml(descriptor, config.emptySummary));
                $select.text(replaceLabel);
                $preview.html(buildPreviewMarkup(attachment));
                updateMobileReadabilityWarning();
            }

            function bindBannerImagePicker(config) {
                var frame = null;

                $(config.select).on('click', function(event) {
                    event.preventDefault();

                    if (frame) {
                        frame.open();
                        return;
                    }

                    frame = wp.media({
                        button: {
                            text: '<?php echo esc_js(__('Use this media', 'vms-sponsorships')); ?>'
                        },
                        library: {
                            type: ['image']
                        },
                        multiple: false,
                        title: config.title
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        updateSelectionState(config, attachment);
                    });

                    frame.open();
                });

                $(config.clear).on('click', function(event) {
                    event.preventDefault();
                    updateSelectionState(config, null);
                });
            }

            $.each(fieldConfigs, function(index, config) {
                bindBannerImagePicker(config);
            });

            updateMobileReadabilityWarning();
        });
        </script>
        <?php
    }

    private function banner_image_field_state($option_name, $args = array()) {
        $args = wp_parse_args($args, array(
            'legacy_fallback' => 0,
            'empty_summary' => __('No image selected.', 'vms-sponsorships'),
        ));

        $image_id = absint(get_option($option_name, 0));
        if ($image_id <= 0 && !empty($args['legacy_fallback'])) {
            $image_id = absint($args['legacy_fallback']);
        }

        $descriptor = $this->banner_image_descriptor($image_id);

        return array(
            'image_id' => $image_id,
            'summary_html' => $this->banner_image_summary_html($descriptor, $args['empty_summary']),
            'preview_html' => $this->banner_image_preview_markup($image_id),
            'descriptor' => $descriptor,
            'is_wide' => !empty($descriptor['id']) && 'wide_banner' === ($descriptor['layout_key'] ?? ''),
        );
    }

    private function banner_image_summary_html($descriptor, $empty_summary) {
        if (empty($descriptor['id'])) {
            return esc_html((string) $empty_summary);
        }

        $lines = array(
            sprintf(
                '<strong>%s</strong> %s',
                esc_html__('Selected image:', 'vms-sponsorships'),
                esc_html($descriptor['label'])
            ),
        );

        if (!empty($descriptor['width']) && !empty($descriptor['height'])) {
            $lines[] = sprintf(
                '<strong>%s</strong> %s',
                esc_html__('Dimensions:', 'vms-sponsorships'),
                esc_html(sprintf('%d x %d px', absint($descriptor['width']), absint($descriptor['height'])))
            );
        }

        if (!empty($descriptor['aspect_ratio_display'])) {
            $lines[] = sprintf(
                '<strong>%s</strong> %s',
                esc_html__('Aspect ratio:', 'vms-sponsorships'),
                esc_html($descriptor['aspect_ratio_display'])
            );
        }

        $lines[] = sprintf(
            '<strong>%s</strong> %s',
            esc_html__('Classification:', 'vms-sponsorships'),
            esc_html($descriptor['layout_label'])
        );

        $lines[] = sprintf(
            '<strong>%s</strong> %s',
            esc_html__('Detected layout type:', 'vms-sponsorships'),
            esc_html($descriptor['layout_key'])
        );

        return implode('<br>', $lines);
    }

    private function banner_image_descriptor($image_id) {
        $image_id = absint($image_id);
        if ($image_id <= 0) {
            return array(
                'id' => 0,
                'label' => '',
                'width' => 0,
                'height' => 0,
                'aspect_ratio' => 0.0,
                'aspect_ratio_display' => '',
                'layout_key' => '',
                'layout_label' => '',
            );
        }

        $attachment = get_post($image_id);
        $label = $attachment instanceof WP_Post
            ? trim((string) $attachment->post_title)
            : '';

        if ($label === '' && function_exists('get_attached_file')) {
            $label = wp_basename((string) get_attached_file($image_id));
        }

        if ($label === '') {
            $label = __('Untitled media item', 'vms-sponsorships');
        }

        $metadata = wp_get_attachment_metadata($image_id);
        $width = absint($metadata['width'] ?? 0);
        $height = absint($metadata['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            $image_src = wp_get_attachment_image_src($image_id, 'full');
            if (is_array($image_src)) {
                $width = absint($image_src[1] ?? 0);
                $height = absint($image_src[2] ?? 0);
            }
        }

        $aspect_ratio = $width > 0 && $height > 0
            ? round($width / max(1, $height), 3)
            : 0.0;
        $layout_key = $this->classify_banner_image_layout($aspect_ratio);

        return array(
            'id' => $image_id,
            'label' => $label,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $aspect_ratio,
            'aspect_ratio_display' => $aspect_ratio > 0 ? number_format_i18n($aspect_ratio, 2) . ':1' : '',
            'layout_key' => $layout_key,
            'layout_label' => $this->banner_image_layout_label($layout_key),
        );
    }

    private function classify_banner_image_layout($aspect_ratio) {
        $aspect_ratio = (float) $aspect_ratio;

        if ($aspect_ratio >= 2.0) {
            return 'wide_banner';
        }

        if ($aspect_ratio > 0 && $aspect_ratio < 0.8) {
            return 'portrait_mobile';
        }

        return 'square_or_standard';
    }

    private function banner_image_layout_label($layout_key) {
        switch ((string) $layout_key) {
            case 'wide_banner':
                return __('Wide banner', 'vms-sponsorships');
            case 'portrait_mobile':
                return __('Portrait / mobile', 'vms-sponsorships');
            case 'square_or_standard':
            default:
                return __('Square or standard', 'vms-sponsorships');
        }
    }

    private function banner_image_preview_markup($image_id) {
        $image_id = absint($image_id);
        if ($image_id <= 0) {
            return '';
        }

        return (string) wp_get_attachment_image($image_id, 'medium', false, array(
            'alt' => '',
            'style' => 'display:block;max-width:280px;height:auto;border-radius:12px;',
        ));
    }

    private function render_banner_image_setting_field($args) {
        $args = wp_parse_args($args, array(
            'field_id' => '',
            'field_name' => '',
            'select_button_id' => '',
            'clear_button_id' => '',
            'summary_id' => '',
            'preview_id' => '',
            'image_id' => 0,
            'summary_html' => __('No image selected.', 'vms-sponsorships'),
            'preview_html' => '',
            'description' => '',
            'descriptor' => array(),
        ));
        $descriptor = is_array($args['descriptor']) ? $args['descriptor'] : array();
        ?>
        <input type="hidden" id="<?php echo esc_attr($args['field_id']); ?>" name="<?php echo esc_attr($args['field_name']); ?>" value="<?php echo esc_attr(absint($args['image_id'])); ?>" data-vms-layout-key="<?php echo esc_attr($descriptor['layout_key'] ?? ''); ?>" data-vms-width="<?php echo esc_attr(absint($descriptor['width'] ?? 0)); ?>" data-vms-height="<?php echo esc_attr(absint($descriptor['height'] ?? 0)); ?>">
        <button type="button" class="button" id="<?php echo esc_attr($args['select_button_id']); ?>"><?php echo esc_html(absint($args['image_id']) > 0 ? __('Select / Replace Image', 'vms-sponsorships') : __('Select Image', 'vms-sponsorships')); ?></button>
        <button type="button" class="button" id="<?php echo esc_attr($args['clear_button_id']); ?>"><?php esc_html_e('Clear Image', 'vms-sponsorships'); ?></button>
        <?php if ($args['description'] !== '') : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <p id="<?php echo esc_attr($args['summary_id']); ?>" class="description"><?php echo wp_kses_post($args['summary_html']); ?></p>
        <div id="<?php echo esc_attr($args['preview_id']); ?>" style="margin-top:12px;">
            <?php
            if ($args['preview_html'] !== '') {
                echo wp_kses_post($args['preview_html']);
            }
            ?>
        </div>
        <?php
    }

    private function event_page_placement_mode_options() {
        return array(
            VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_DISABLED => array(
                'label' => __('Disabled', 'vms-sponsorships'),
                'description' => __('Do not auto-place the sponsor banner on event pages.', 'vms-sponsorships'),
            ),
            VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_MANUAL => array(
                'label' => __('Manual shortcode only', 'vms-sponsorships'),
                'description' => __('Only show the banner where you intentionally place the shortcode yourself.', 'vms-sponsorships'),
            ),
            VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_AUTOMATIC => array(
                'label' => __('Automatic event-page banner', 'vms-sponsorships'),
                'description' => __('Automatically inject the banner on supported The Events Calendar single event pages when the template hook is present.', 'vms-sponsorships'),
            ),
        );
    }

    private function sanitize_event_page_placement_mode($mode) {
        $mode = sanitize_key((string) $mode);
        $allowed = array_keys($this->event_page_placement_mode_options());

        return in_array($mode, $allowed, true)
            ? $mode
            : VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_MANUAL;
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

    private function assignment_scope_value($assignment) {
        $value = is_object($assignment)
            ? (string) ($assignment->assignment_scope ?? '')
            : (string) ($assignment['assignment_scope'] ?? '');
        $value = sanitize_key($value);

        return in_array($value, $this->repo->assignment_scopes(), true) ? $value : 'event';
    }

    private function assignment_scope_summary($assignment) {
        $scope = $this->assignment_scope_value($assignment);
        if ($scope === 'date_range') {
            $label = is_object($assignment)
                ? sanitize_text_field((string) ($assignment->scope_label ?? ''))
                : sanitize_text_field((string) ($assignment['scope_label'] ?? ''));
            $start = is_object($assignment) ? (string) ($assignment->starts_at ?? '') : (string) ($assignment['starts_at'] ?? '');
            $end = is_object($assignment) ? (string) ($assignment->ends_at ?? '') : (string) ($assignment['ends_at'] ?? '');
            $range = $this->assignment_scope_date_range($start, $end);

            if ($label !== '' && $range !== '') {
                return $label . ', ' . $range;
            }

            if ($label !== '') {
                return $label;
            }

            return $range !== '' ? $range : __('Season / date range', 'vms-sponsorships');
        }

        $event_id = is_object($assignment)
            ? absint($assignment->event_id ?? 0)
            : absint($assignment['event_id'] ?? 0);

        return $event_id > 0 ? $this->event_label($event_id) : __('No linked event', 'vms-sponsorships');
    }

    private function assignment_scope_date_range($start, $end) {
        $start_label = $this->format_date($start);
        $end_label = $this->format_date($end);
        if ($start_label === __('Not set', 'vms-sponsorships') || $end_label === __('Not set', 'vms-sponsorships')) {
            return '';
        }

        return $start_label . ' - ' . $end_label;
    }

    private function assignment_shortcode_summary($assignment) {
        $scope = $this->assignment_scope_value($assignment);
        if ($scope === 'date_range') {
            $season_id = absint($assignment->season_id ?? 0);
            if ($season_id > 0) {
                return '<code>[vms_sponsor_season season_id="' . esc_attr($season_id) . '" slot="' . esc_attr($assignment->slot_key) . '"]</code>';
            }

            return esc_html__('Automatic event-date matching', 'vms-sponsorships');
        }

        $event_id = absint($assignment->event_id ?? 0);
        if ($event_id <= 0) {
            return esc_html__('Link an event to enable event shortcodes', 'vms-sponsorships');
        }

        return '<code>[vms_sponsor_slot event_id="' . esc_attr($event_id) . '" slot="' . esc_attr($assignment->slot_key) . '"]</code>';
    }

    private function date_input_value($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }

        return wp_date('Y-m-d', $timestamp);
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

    private function application_can_be_approved_on_create($application) {
        if (!is_object($application)) {
            return false;
        }

        return !in_array(
            sanitize_key((string) ($application->status ?? '')),
            array('approved', 'declined', 'withdrawn', 'archived'),
            true
        );
    }

    private function default_payment_status_for_application($application) {
        if (!is_object($application)) {
            return 'unpaid';
        }

        $type = sanitize_key((string) ($application->consideration_type ?? ''));

        if ('trade_in_kind' === $type) {
            return 'in_kind';
        }

        if ('cash_and_trade' === $type) {
            return 'pending';
        }

        return 'unpaid';
    }

    private function default_assignment_asset_status($application, $package) {
        $application_id = is_object($application) ? absint($application->id ?? 0) : 0;
        $latest_asset_status = $this->latest_application_asset_status($application_id);
        if ($latest_asset_status !== '') {
            return $latest_asset_status;
        }

        return $this->package_expects_creative_assets($package)
            ? 'not_submitted'
            : 'not_required';
    }

    private function default_assignment_fulfillment_status($application, $package) {
        return 'not_started';
    }

    private function normalize_assignment_asset_status($status, $assignment_id = 0, $application_id = 0) {
        $status = sanitize_key((string) $status);
        $allowed = $this->repo->assignment_asset_statuses();
        if (in_array($status, $allowed, true)) {
            return $status;
        }

        if ($status === 'pending') {
            return $this->assignment_context_has_uploaded_assets($assignment_id, $application_id)
                ? 'pending_review'
                : 'not_submitted';
        }

        return 'not_submitted';
    }

    private function normalize_fulfillment_status($status) {
        return $this->repo->sanitize_enum($status, $this->repo->fulfillment_statuses(), 'not_started');
    }

    private function default_in_kind_value_for_application($application) {
        if (!is_object($application)) {
            return 0.0;
        }

        $type = sanitize_key((string) ($application->consideration_type ?? ''));
        if (!in_array($type, array('trade_in_kind', 'cash_and_trade'), true)) {
            return 0.0;
        }

        return (float) ($application->estimated_trade_value ?? 0);
    }

    private function application_assignment_notes($application) {
        if (!is_object($application)) {
            return '';
        }

        $lines = array(
            sprintf(__('Source application #%d', 'vms-sponsorships'), absint($application->id)),
        );

        if (!empty($application->contact_name) || !empty($application->email)) {
            $contact_name = sanitize_text_field((string) ($application->contact_name ?? ''));
            $contact_email = sanitize_email((string) ($application->email ?? ''));
            $contact_line = $contact_name;

            if ($contact_email !== '') {
                $contact_line = $contact_name !== ''
                    ? $contact_name . ' <' . $contact_email . '>'
                    : $contact_email;
            }

            if ($contact_line !== '') {
                $lines[] = sprintf(__('Contact: %s', 'vms-sponsorships'), $contact_line);
            }
        }

        if (!empty($application->phone)) {
            $lines[] = sprintf(__('Phone: %s', 'vms-sponsorships'), sanitize_text_field((string) $application->phone));
        }

        if (!empty($application->website_url)) {
            $lines[] = sprintf(__('Website: %s', 'vms-sponsorships'), esc_url_raw((string) $application->website_url));
        }

        $context_bits = array(
            $this->scope_label($application->scope ?? 'event'),
        );
        if (!empty($application->event_id)) {
            $context_bits[] = $this->event_label($application->event_id);
        }
        if (!empty($application->requested_slot_key)) {
            $context_bits[] = $this->slot_label($application->requested_slot_key);
        }
        if (!empty($application->requested_package_id)) {
            $context_bits[] = $this->package_name($application->requested_package_id);
        }
        $lines[] = sprintf(__('Requested context: %s', 'vms-sponsorships'), implode(' · ', array_filter($context_bits)));

        $consideration = $this->consideration_type_label($application->consideration_type ?? 'not_sure');
        if (null !== $application->estimated_trade_value && '' !== trim((string) $application->estimated_trade_value)) {
            $consideration .= ' · ' . sprintf(
                __('Estimated value: %s', 'vms-sponsorships'),
                '$' . number_format_i18n((float) $application->estimated_trade_value, 2)
            );
        }
        $lines[] = sprintf(__('Consideration: %s', 'vms-sponsorships'), $consideration);

        if (!empty($application->sponsor_message)) {
            $lines[] = __('Application message:', 'vms-sponsorships');
            $lines[] = sanitize_textarea_field((string) $application->sponsor_message);
        }

        if (!empty($application->trade_offer_details)) {
            $lines[] = __('Trade / in-kind details:', 'vms-sponsorships');
            $lines[] = sanitize_textarea_field((string) $application->trade_offer_details);
        }

        return implode("\n", array_filter($lines));
    }

    private function latest_application_asset_status($application_id) {
        $application_id = absint($application_id);
        if ($application_id <= 0) {
            return '';
        }

        $assets = $this->repo->get_assets(array(
            'application_id' => $application_id,
            'status' => array('pending_review', 'approved', 'rejected', 'needs_revision'),
            'limit' => 1,
        ));
        if (empty($assets)) {
            return '';
        }

        $status = sanitize_key((string) ($assets[0]->status ?? ''));
        return $this->repo->sanitize_enum($status, $this->repo->assignment_asset_statuses(), '');
    }

    private function assignment_context_has_uploaded_assets($assignment_id = 0, $application_id = 0) {
        if (
            $assignment_id > 0
            && !empty($this->repo->get_assets(array(
                'assignment_id' => $assignment_id,
                'status' => array('pending_review', 'approved', 'rejected', 'needs_revision'),
                'limit' => 1,
            )))
        ) {
            return true;
        }

        if (
            $application_id > 0
            && !empty($this->repo->get_assets(array(
                'application_id' => $application_id,
                'status' => array('pending_review', 'approved', 'rejected', 'needs_revision'),
                'limit' => 1,
            )))
        ) {
            return true;
        }

        return false;
    }

    private function package_expects_creative_assets($package) {
        if (!is_object($package)) {
            return true;
        }

        if (!empty($package->includes_physical_banner) || !empty($package->public_display_enabled) || !empty($package->email_display_enabled)) {
            return true;
        }

        $required_assets = trim((string) ($package->required_assets ?? ''));
        if ($required_assets === '') {
            return false;
        }

        $decoded = json_decode($required_assets, true);
        if (is_array($decoded)) {
            return !empty(array_filter(array_map('sanitize_key', $decoded)));
        }

        return true;
    }

    private function assignment_asset_status_label($status) {
        $labels = array(
            'not_submitted' => __('Not submitted', 'vms-sponsorships'),
            'not_required' => __('Not required', 'vms-sponsorships'),
            'pending_review' => __('Pending review', 'vms-sponsorships'),
            'approved' => __('Approved', 'vms-sponsorships'),
            'rejected' => __('Rejected', 'vms-sponsorships'),
            'needs_revision' => __('Needs revision', 'vms-sponsorships'),
            'archived' => __('Archived', 'vms-sponsorships'),
        );

        $status = sanitize_key((string) $status);

        return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    private function fulfillment_status_label($status) {
        $labels = array(
            'not_started' => __('Not started', 'vms-sponsorships'),
            'in_progress' => __('In progress', 'vms-sponsorships'),
            'complete' => __('Complete', 'vms-sponsorships'),
            'waived' => __('Waived', 'vms-sponsorships'),
            'overdue' => __('Overdue', 'vms-sponsorships'),
        );

        $status = sanitize_key((string) $status);

        return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    private function assignment_event_options($selected_event_id = 0) {
        $selected_event_id = absint($selected_event_id);
        $post_types = array_values(array_filter($this->assignment_event_post_types(), 'post_type_exists'));
        if (empty($post_types)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => $post_types,
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $indexed_posts = array();
        foreach ((array) $posts as $post) {
            $indexed_posts[absint($post->ID)] = $post;
        }

        if ($selected_event_id > 0 && !isset($indexed_posts[$selected_event_id])) {
            $selected_post = get_post($selected_event_id);
            if ($selected_post instanceof WP_Post) {
                $indexed_posts[$selected_event_id] = $selected_post;
            }
        }

        $options = array();
        foreach ($indexed_posts as $post) {
            $title = get_the_title($post);
            if (!is_string($title) || $title === '') {
                $title = sprintf(__('Post #%d', 'vms-sponsorships'), absint($post->ID));
            }

            $type_label = get_post_type_object($post->post_type);
            $type_name = $type_label ? $type_label->labels->singular_name : $post->post_type;
            $date_label = $this->event_option_date_label($post);
            $option_label = $title . ' · ' . $date_label . ' · ' . $type_name . ' · ID ' . absint($post->ID);

            $options[] = array(
                'id' => absint($post->ID),
                'label' => $option_label,
                'search' => $this->event_option_search_index($post, $title, $date_label, $type_name),
            );
        }

        return $options;
    }

    private function assignment_event_post_types() {
        return apply_filters('vms_sponsorships_event_post_types', array(
            'event',
            'events',
            'tribe_events',
            'vms_event',
            'vms_event_plan',
        ));
    }

    private function event_option_date_label($post) {
        $post_id = absint($post->ID ?? 0);
        if ($post_id <= 0) {
            return __('Date not set', 'vms-sponsorships');
        }

        if (function_exists('tribe_get_start_date') && get_post_type($post_id) === 'tribe_events') {
            $date = tribe_get_start_date($post_id, false, get_option('date_format'));
            if (is_string($date) && $date !== '') {
                return $date;
            }
        }

        if (get_post_type($post_id) === 'vms_event_plan') {
            $event_date = trim((string) get_post_meta($post_id, '_vms_event_date', true));
            if ($event_date !== '') {
                $timestamp = strtotime($event_date);
                if ($timestamp) {
                    return wp_date(get_option('date_format'), $timestamp);
                }
                return $event_date;
            }
        }

        $timestamp = get_post_time('U', false, $post_id);
        if ($timestamp) {
            return wp_date(get_option('date_format'), $timestamp);
        }

        return __('Date not set', 'vms-sponsorships');
    }

    private function event_option_search_index($post, $title, $date_label, $type_name) {
        $post_id = absint($post->ID ?? 0);
        $parts = array(
            $title,
            $date_label,
            $type_name,
            $post->post_type ?? '',
            (string) $post_id,
            'id ' . $post_id,
            '#' . $post_id,
        );

        $parts = array_merge($parts, $this->event_option_date_search_tokens($post));

        return strtolower(implode(' ', array_filter(array_map('trim', $parts))));
    }

    private function event_option_date_search_tokens($post) {
        $post_id = absint($post->ID ?? 0);
        if ($post_id <= 0) {
            return array();
        }

        $tokens = array();
        if (function_exists('tribe_get_start_date') && get_post_type($post_id) === 'tribe_events') {
            $tokens[] = tribe_get_start_date($post_id, false, 'Y-m-d');
            $tokens[] = tribe_get_start_date($post_id, false, 'm/d/Y');
        } elseif (get_post_type($post_id) === 'vms_event_plan') {
            $event_date = trim((string) get_post_meta($post_id, '_vms_event_date', true));
            if ($event_date !== '') {
                $timestamp = strtotime($event_date);
                if ($timestamp) {
                    $tokens[] = wp_date('Y-m-d', $timestamp);
                    $tokens[] = wp_date('m/d/Y', $timestamp);
                } else {
                    $tokens[] = $event_date;
                }
            }
        } else {
            $timestamp = get_post_time('U', false, $post_id);
            if ($timestamp) {
                $tokens[] = wp_date('Y-m-d', $timestamp);
                $tokens[] = wp_date('m/d/Y', $timestamp);
            }
        }

        return array_values(array_filter(array_unique(array_map('strval', $tokens))));
    }

    private function redirect_message($message, $page, $args = array()) {
        $args['vms_sponsorships_message'] = sanitize_key($message);
        wp_safe_redirect($this->page_url($page, $args));
        exit;
    }

    private function redirect_error($error, $args = array()) {
        $page = !empty($args['page'])
            ? sanitize_key((string) $args['page'])
            : (!empty($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : self::ROOT_SLUG);
        unset($args['page']);

        $args['vms_sponsorships_error'] = sanitize_key($error);
        wp_safe_redirect($this->page_url($page, $args));
        exit;
    }
}
