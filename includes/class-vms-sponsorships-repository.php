<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Repository {
    /** @var array<string, mixed> */
    private $request_cache = array();

    public function table($name) {
        global $wpdb;
        $allowed = array(
            'packages'     => 'vms_sponsorship_packages',
            'applications' => 'vms_sponsorship_applications',
            'assignments'  => 'vms_sponsorship_assignments',
            'assets'       => 'vms_sponsor_assets',
            'metrics'      => 'vms_sponsor_metrics',
            'fulfillment'  => 'vms_sponsor_fulfillment_items',
        );

        if (!isset($allowed[$name])) {
            return '';
        }

        return $wpdb->prefix . $allowed[$name];
    }

    public function now() {
        return current_time('mysql');
    }

    public function package_statuses() {
        return array('active', 'inactive');
    }

    public function application_statuses() {
        return array('draft', 'submitted', 'under_review', 'info_requested', 'approved', 'declined', 'waitlisted', 'withdrawn', 'archived');
    }

    public function application_consideration_types() {
        return array('cash', 'trade_in_kind', 'cash_and_trade', 'not_sure');
    }

    public function assignment_statuses() {
        return array('open', 'prospect', 'confirmed', 'paid', 'fulfilled', 'declined', 'cancelled', 'archived');
    }

    public function payment_statuses() {
        return array('not_required', 'unpaid', 'invoice_sent', 'pending', 'paid', 'in_kind', 'refunded', 'cancelled');
    }

    public function asset_statuses() {
        return array('pending_review', 'approved', 'rejected', 'needs_revision', 'archived');
    }

    public function fulfillment_statuses() {
        return array('not_started', 'in_progress', 'complete', 'waived', 'overdue');
    }

    public function scopes() {
        return array('event', 'feature', 'season', 'venue', 'newsletter');
    }

    public function get_packages($active_only = false) {
        global $wpdb;
        $table = $this->table('packages');

        if ($active_only) {
            return $wpdb->get_results("SELECT * FROM {$table} WHERE active = 1 ORDER BY scope ASC, base_price DESC, name ASC");
        }

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY active DESC, scope ASC, base_price DESC, name ASC");
    }

    public function get_package($id) {
        global $wpdb;
        $table = $this->table('packages');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id)));
    }

    public function get_package_by_slug($slug) {
        global $wpdb;
        $table = $this->table('packages');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", sanitize_title($slug)));
    }

    public function upsert_package($data) {
        global $wpdb;
        $table = $this->table('packages');
        $now = $this->now();
        $id = isset($data['id']) ? absint($data['id']) : 0;

        $row = array(
            'name' => sanitize_text_field($data['name'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? ($data['name'] ?? '')),
            'scope' => $this->sanitize_enum($data['scope'] ?? 'event', $this->scopes(), 'event'),
            'base_price' => $this->sanitize_money($data['base_price'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
            'includes_physical_banner' => !empty($data['includes_physical_banner']) ? 1 : 0,
            'counts_toward_banner_cap' => !empty($data['counts_toward_banner_cap']) ? 1 : 0,
            'requires_approval' => !empty($data['requires_approval']) ? 1 : 0,
            'public_display_enabled' => !empty($data['public_display_enabled']) ? 1 : 0,
            'email_display_enabled' => !empty($data['email_display_enabled']) ? 1 : 0,
            'report_included' => !empty($data['report_included']) ? 1 : 0,
            'required_assets' => isset($data['required_assets']) ? wp_kses_post($data['required_assets']) : '',
            'fulfillment_template' => isset($data['fulfillment_template']) ? wp_kses_post($data['fulfillment_template']) : '',
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'updated_at' => $now,
        );

        if (!$row['name']) {
            return new WP_Error('vms_sponsorships_missing_package_name', __('Package name is required.', 'vms-sponsorships'));
        }

        if (!$row['slug']) {
            $row['slug'] = sanitize_title($row['name']);
        }

        if ($id > 0) {
            $wpdb->update($table, $row, array('id' => $id));
            return $id;
        }

        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public function create_application($data) {
        global $wpdb;
        $table = $this->table('applications');
        $now = $this->now();

        $email = sanitize_email($data['email'] ?? '');
        $business_name = sanitize_text_field($data['business_name'] ?? '');

        if (!$business_name || !$email || !is_email($email)) {
            return new WP_Error('vms_sponsorships_invalid_application', __('Business name and a valid email are required.', 'vms-sponsorships'));
        }

        $row = array(
            'vendor_id' => !empty($data['vendor_id']) ? absint($data['vendor_id']) : null,
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : get_current_user_id(),
            'event_id' => !empty($data['event_id']) ? absint($data['event_id']) : null,
            'season_id' => !empty($data['season_id']) ? absint($data['season_id']) : null,
            'requested_package_id' => !empty($data['requested_package_id']) ? absint($data['requested_package_id']) : null,
            'requested_slot_key' => !empty($data['requested_slot_key']) ? sanitize_key($data['requested_slot_key']) : null,
            'consideration_type' => $this->sanitize_enum($data['consideration_type'] ?? 'not_sure', $this->application_consideration_types(), 'not_sure'),
            'trade_offer_details' => sanitize_textarea_field($data['trade_offer_details'] ?? ''),
            'estimated_trade_value' => '' !== trim((string) ($data['estimated_trade_value'] ?? '')) ? $this->sanitize_money($data['estimated_trade_value']) : null,
            'scope' => $this->sanitize_enum($data['scope'] ?? 'event', $this->scopes(), 'event'),
            'status' => $this->sanitize_enum($data['status'] ?? 'submitted', $this->application_statuses(), 'submitted'),
            'business_name' => $business_name,
            'contact_name' => sanitize_text_field($data['contact_name'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'website_url' => esc_url_raw($data['website_url'] ?? ''),
            'business_category' => sanitize_text_field($data['business_category'] ?? ''),
            'sponsor_message' => sanitize_textarea_field($data['sponsor_message'] ?? ''),
            'decline_reason_private' => '',
            'reviewed_by' => null,
            'submitted_at' => $now,
            'reviewed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public function get_applications($status = '', $limit = 50) {
        global $wpdb;
        $table = $this->table('applications');
        $limit = max(1, min(200, absint($limit)));

        if ($status && in_array($status, $this->application_statuses(), true)) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY submitted_at DESC LIMIT %d", $status, $limit));
        }

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY submitted_at DESC LIMIT %d", $limit));
    }

    public function get_application($id) {
        global $wpdb;
        $table = $this->table('applications');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id)));
    }

    public function update_application_status($id, $status, $decline_reason = '') {
        global $wpdb;
        $table = $this->table('applications');
        $status = $this->sanitize_enum($status, $this->application_statuses(), 'submitted');
        $now = $this->now();

        $data = array(
            'status' => $status,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => $now,
            'updated_at' => $now,
        );

        if ('declined' === $status) {
            $data['decline_reason_private'] = sanitize_textarea_field($decline_reason);
        } else {
            $data['decline_reason_private'] = '';
        }

        return $wpdb->update($table, $data, array('id' => absint($id)));
    }

    public function create_assignment($data) {
        global $wpdb;
        $table = $this->table('assignments');
        $now = $this->now();

        $display_name = sanitize_text_field($data['sponsor_display_name'] ?? '');
        if (!$display_name) {
            return new WP_Error('vms_sponsorships_missing_sponsor_name', __('Sponsor display name is required.', 'vms-sponsorships'));
        }

        $package_id = !empty($data['package_id']) ? absint($data['package_id']) : null;
        $package = $package_id ? $this->get_package($package_id) : null;

        $physical_banner_included = isset($data['physical_banner_included'])
            ? (!empty($data['physical_banner_included']) ? 1 : 0)
            : ($package && (int) $package->includes_physical_banner ? 1 : 0);

        $row = array(
            'vendor_id' => !empty($data['vendor_id']) ? absint($data['vendor_id']) : null,
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : get_current_user_id(),
            'application_id' => !empty($data['application_id']) ? absint($data['application_id']) : null,
            'event_id' => !empty($data['event_id']) ? absint($data['event_id']) : null,
            'season_id' => !empty($data['season_id']) ? absint($data['season_id']) : null,
            'package_id' => $package_id,
            'slot_key' => sanitize_key($data['slot_key'] ?? 'presenting'),
            'status' => $this->sanitize_enum($data['status'] ?? 'prospect', $this->assignment_statuses(), 'prospect'),
            'sponsor_display_name' => $display_name,
            'sponsor_tagline' => sanitize_text_field($data['sponsor_tagline'] ?? ''),
            'sponsor_url' => esc_url_raw($data['sponsor_url'] ?? ''),
            'amount' => $this->sanitize_money($data['amount'] ?? ($package ? $package->base_price : 0)),
            'in_kind_value' => $this->sanitize_money($data['in_kind_value'] ?? 0),
            'payment_status' => $this->sanitize_enum($data['payment_status'] ?? 'unpaid', $this->payment_statuses(), 'unpaid'),
            'public_display_enabled' => !empty($data['public_display_enabled']) ? 1 : 0,
            'placeholder_enabled' => isset($data['placeholder_enabled']) ? (!empty($data['placeholder_enabled']) ? 1 : 0) : 1,
            'physical_banner_included' => $physical_banner_included,
            'banner_slot' => sanitize_key($data['banner_slot'] ?? ''),
            'asset_status' => $this->sanitize_enum($data['asset_status'] ?? 'pending_review', $this->asset_statuses(), 'pending_review'),
            'fulfillment_status' => $this->sanitize_enum($data['fulfillment_status'] ?? 'not_started', $this->fulfillment_statuses(), 'not_started'),
            'report_status' => sanitize_key($data['report_status'] ?? 'not_ready'),
            'starts_at' => !empty($data['starts_at']) ? sanitize_text_field($data['starts_at']) : null,
            'ends_at' => !empty($data['ends_at']) ? sanitize_text_field($data['ends_at']) : null,
            'internal_notes' => sanitize_textarea_field($data['internal_notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        );

        if ($row['event_id'] && $row['physical_banner_included'] && !$this->event_has_banner_capacity($row['event_id'], 0)) {
            return new WP_Error('vms_sponsorships_banner_cap_exceeded', __('This event has reached its physical sponsor banner cap.', 'vms-sponsorships'));
        }

        $wpdb->insert($table, $row);
        $assignment_id = (int) $wpdb->insert_id;

        if ($assignment_id && $package) {
            $this->create_fulfillment_from_package($assignment_id, $package);
        }

        if ($assignment_id) {
            $this->bust_display_cache();
        }

        return $assignment_id;
    }

    public function update_assignment($id, $data) {
        global $wpdb;
        $table = $this->table('assignments');
        $current = $this->get_assignment($id);
        if (!$current) {
            return new WP_Error('vms_sponsorships_assignment_missing', __('Sponsorship assignment not found.', 'vms-sponsorships'));
        }

        $row = array('updated_at' => $this->now());

        $map_text = array('sponsor_display_name', 'sponsor_tagline', 'internal_notes');
        foreach ($map_text as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = 'internal_notes' === $field ? sanitize_textarea_field($data[$field]) : sanitize_text_field($data[$field]);
            }
        }

        if (array_key_exists('sponsor_url', $data)) {
            $row['sponsor_url'] = esc_url_raw($data['sponsor_url']);
        }

        $ints = array('vendor_id', 'user_id', 'application_id', 'event_id', 'season_id', 'package_id');
        foreach ($ints as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = !empty($data[$field]) ? absint($data[$field]) : null;
            }
        }

        if (array_key_exists('slot_key', $data)) {
            $row['slot_key'] = sanitize_key($data['slot_key']);
        }
        if (array_key_exists('banner_slot', $data)) {
            $row['banner_slot'] = sanitize_key($data['banner_slot']);
        }
        if (array_key_exists('status', $data)) {
            $row['status'] = $this->sanitize_enum($data['status'], $this->assignment_statuses(), $current->status);
        }
        if (array_key_exists('payment_status', $data)) {
            $row['payment_status'] = $this->sanitize_enum($data['payment_status'], $this->payment_statuses(), $current->payment_status);
        }
        if (array_key_exists('asset_status', $data)) {
            $row['asset_status'] = $this->sanitize_enum($data['asset_status'], $this->asset_statuses(), $current->asset_status);
        }
        if (array_key_exists('fulfillment_status', $data)) {
            $row['fulfillment_status'] = $this->sanitize_enum($data['fulfillment_status'], $this->fulfillment_statuses(), $current->fulfillment_status);
        }
        if (array_key_exists('report_status', $data)) {
            $row['report_status'] = sanitize_key($data['report_status']);
        }
        if (array_key_exists('amount', $data)) {
            $row['amount'] = $this->sanitize_money($data['amount']);
        }
        if (array_key_exists('in_kind_value', $data)) {
            $row['in_kind_value'] = $this->sanitize_money($data['in_kind_value']);
        }
        if (array_key_exists('public_display_enabled', $data)) {
            $row['public_display_enabled'] = !empty($data['public_display_enabled']) ? 1 : 0;
        }
        if (array_key_exists('placeholder_enabled', $data)) {
            $row['placeholder_enabled'] = !empty($data['placeholder_enabled']) ? 1 : 0;
        }
        if (array_key_exists('physical_banner_included', $data)) {
            $new_banner = !empty($data['physical_banner_included']) ? 1 : 0;
            $event_id = array_key_exists('event_id', $row) ? $row['event_id'] : $current->event_id;
            if ($event_id && $new_banner && !$this->event_has_banner_capacity($event_id, absint($id))) {
                return new WP_Error('vms_sponsorships_banner_cap_exceeded', __('This event has reached its physical sponsor banner cap.', 'vms-sponsorships'));
            }
            $row['physical_banner_included'] = $new_banner;
        }

        $result = $wpdb->update($table, $row, array('id' => absint($id)));

        if (false !== $result) {
            $this->bust_display_cache();
        }

        return $result;
    }

    public function get_assignment($id) {
        global $wpdb;
        $table = $this->table('assignments');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id)));
    }

    public function get_assignments($args = array()) {
        global $wpdb;
        $table = $this->table('assignments');
        $where = array('1=1');
        $params = array();

        if (!empty($args['event_id'])) {
            $where[] = 'event_id = %d';
            $params[] = absint($args['event_id']);
        }
        if (!empty($args['season_id'])) {
            $where[] = 'season_id = %d';
            $params[] = absint($args['season_id']);
        }
        if (!empty($args['slot_key'])) {
            $where[] = 'slot_key = %s';
            $params[] = sanitize_key($args['slot_key']);
        }
        if (!empty($args['status']) && in_array($args['status'], $this->assignment_statuses(), true)) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if (isset($args['public_display_enabled'])) {
            $where[] = 'public_display_enabled = %d';
            $params[] = !empty($args['public_display_enabled']) ? 1 : 0;
        }

        $limit = !empty($args['limit']) ? max(1, min(200, absint($args['limit']))) : 50;
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function get_public_assignment($event_id, $slot_key = 'presenting') {
        global $wpdb;
        $table = $this->table('assignments');
        $event_id = absint($event_id);
        $slot_key = sanitize_key($slot_key);
        $cache_key = $this->display_cache_key('public_assignment', array($event_id, $slot_key));

        if (array_key_exists($cache_key, $this->request_cache)) {
            return $this->request_cache[$cache_key];
        }

        $cached = get_transient($cache_key);
        if (is_array($cached) && array_key_exists('found', $cached)) {
            $assignment = !empty($cached['found']) && !empty($cached['assignment']) ? (object) $cached['assignment'] : null;
            $this->request_cache[$cache_key] = $assignment;
            return $assignment;
        }

        $valid_statuses = array('confirmed', 'paid', 'fulfilled');
        $placeholders = implode(',', array_fill(0, count($valid_statuses), '%s'));
        $params = array_merge(array($event_id, $slot_key), $valid_statuses);
        $params[] = 1;

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE event_id = %d
               AND slot_key = %s
               AND status IN ($placeholders)
               AND public_display_enabled = %d
             ORDER BY FIELD(payment_status, 'paid', 'in_kind', 'not_required', 'pending', 'invoice_sent', 'unpaid'), created_at DESC
             LIMIT 1",
            $params
        ));

        set_transient(
            $cache_key,
            $assignment ? array('found' => 1, 'assignment' => get_object_vars($assignment)) : array('found' => 0),
            5 * MINUTE_IN_SECONDS
        );
        $this->request_cache[$cache_key] = $assignment;

        return $assignment;
    }

    public function count_event_banners($event_id, $exclude_assignment_id = 0) {
        global $wpdb;
        $table = $this->table('assignments');
        $event_id = absint($event_id);
        $exclude_assignment_id = absint($exclude_assignment_id);

        if ($exclude_assignment_id) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE event_id = %d
                   AND physical_banner_included = 1
                   AND id != %d
                   AND status NOT IN ('declined', 'cancelled', 'archived')",
                $event_id,
                $exclude_assignment_id
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE event_id = %d
               AND physical_banner_included = 1
               AND status NOT IN ('declined', 'cancelled', 'archived')",
            $event_id
        ));
    }

    public function event_has_banner_capacity($event_id, $exclude_assignment_id = 0) {
        $cap = max(0, absint(get_option('vms_sponsorships_max_event_banners', 1)));
        if (0 === $cap) {
            return false;
        }

        return $this->count_event_banners($event_id, $exclude_assignment_id) < $cap;
    }

    public function add_asset($data) {
        global $wpdb;
        $table = $this->table('assets');
        $now = $this->now();
        $attachment_id = !empty($data['attachment_id']) ? absint($data['attachment_id']) : 0;

        if (!$attachment_id) {
            return new WP_Error('vms_sponsorships_missing_asset', __('Attachment ID is required.', 'vms-sponsorships'));
        }

        $row = array(
            'vendor_id' => !empty($data['vendor_id']) ? absint($data['vendor_id']) : null,
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : get_current_user_id(),
            'assignment_id' => !empty($data['assignment_id']) ? absint($data['assignment_id']) : null,
            'application_id' => !empty($data['application_id']) ? absint($data['application_id']) : null,
            'asset_type' => sanitize_key($data['asset_type'] ?? 'logo'),
            'attachment_id' => $attachment_id,
            'status' => $this->sanitize_enum($data['status'] ?? 'pending_review', $this->asset_statuses(), 'pending_review'),
            'reviewed_by' => null,
            'rejection_note' => '',
            'uploaded_at' => $now,
            'reviewed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $wpdb->insert($table, $row);
        $asset_id = (int) $wpdb->insert_id;
        $this->bust_display_cache();

        return $asset_id;
    }

    public function get_assets($args = array()) {
        global $wpdb;
        $table = $this->table('assets');
        $where = array('1=1');
        $params = array();

        if (!empty($args['assignment_id'])) {
            $where[] = 'assignment_id = %d';
            $params[] = absint($args['assignment_id']);
        }
        if (!empty($args['application_id'])) {
            $where[] = 'application_id = %d';
            $params[] = absint($args['application_id']);
        }
        if (!empty($args['asset_type'])) {
            $where[] = 'asset_type = %s';
            $params[] = sanitize_key($args['asset_type']);
        }
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $this->sanitize_enum($args['status'], $this->asset_statuses(), 'pending_review');
        }

        $limit = !empty($args['limit']) ? max(1, min(200, absint($args['limit']))) : 50;
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY uploaded_at DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function get_approved_logo_url($assignment_id) {
        $assignment_id = absint($assignment_id);
        $cache_key = $this->display_cache_key('approved_logo', array($assignment_id));

        if (array_key_exists($cache_key, $this->request_cache)) {
            return $this->request_cache[$cache_key];
        }

        $cached = get_transient($cache_key);
        if (false !== $cached) {
            $logo_url = is_string($cached) ? $cached : '';
            $this->request_cache[$cache_key] = $logo_url;
            return $logo_url;
        }

        $assets = $this->get_assets(array(
            'assignment_id' => $assignment_id,
            'asset_type' => 'logo',
            'status' => 'approved',
            'limit' => 1,
        ));

        if (empty($assets)) {
            set_transient($cache_key, '', 5 * MINUTE_IN_SECONDS);
            $this->request_cache[$cache_key] = '';
            return '';
        }

        $logo_url = wp_get_attachment_image_url((int) $assets[0]->attachment_id, 'medium');
        $logo_url = is_string($logo_url) ? $logo_url : '';

        set_transient($cache_key, $logo_url, 5 * MINUTE_IN_SECONDS);
        $this->request_cache[$cache_key] = $logo_url;

        return $logo_url;
    }

    public function get_asset($id) {
        global $wpdb;
        $table = $this->table('assets');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", absint($id)));
    }

    public function update_asset_status($id, $status, $rejection_note = '') {
        global $wpdb;
        $table = $this->table('assets');
        $status = $this->sanitize_enum($status, $this->asset_statuses(), 'pending_review');
        $result = $wpdb->update($table, array(
            'status' => $status,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => $this->now(),
            'rejection_note' => sanitize_textarea_field($rejection_note),
            'updated_at' => $this->now(),
        ), array('id' => absint($id)));

        if (false !== $result) {
            $this->bust_display_cache();
        }

        return $result;
    }

    public function create_fulfillment_from_package($assignment_id, $package) {
        if (empty($package->fulfillment_template)) {
            return;
        }

        $template = json_decode($package->fulfillment_template, true);
        if (!is_array($template)) {
            return;
        }

        global $wpdb;
        $table = $this->table('fulfillment');
        $now = $this->now();

        foreach ($template as $item) {
            if (empty($item['key']) || empty($item['label'])) {
                continue;
            }

            $wpdb->insert($table, array(
                'assignment_id' => absint($assignment_id),
                'item_key' => sanitize_key($item['key']),
                'label' => sanitize_text_field($item['label']),
                'status' => 'not_started',
                'due_at' => null,
                'completed_at' => null,
                'completed_by' => null,
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    public function get_fulfillment_items($assignment_id) {
        global $wpdb;
        $table = $this->table('fulfillment');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE assignment_id = %d ORDER BY id ASC",
            absint($assignment_id)
        ));
    }

    public function update_fulfillment_item($id, $status, $notes = '') {
        global $wpdb;
        $table = $this->table('fulfillment');
        $status = $this->sanitize_enum($status, $this->fulfillment_statuses(), 'not_started');
        $data = array(
            'status' => $status,
            'notes' => sanitize_textarea_field($notes),
            'updated_at' => $this->now(),
        );

        if ('complete' === $status) {
            $data['completed_at'] = $this->now();
            $data['completed_by'] = get_current_user_id();
        }

        return $wpdb->update($table, $data, array('id' => absint($id)));
    }

    public function increment_metric($assignment_id, $metric_type, $event_id = null, $season_id = null, $amount = 1) {
        global $wpdb;
        $table = $this->table('metrics');
        $assignment_id = absint($assignment_id);
        $metric_type = sanitize_key($metric_type);
        $metric_date = current_time('Y-m-d');
        $amount = max(1, absint($amount));
        $now = $this->now();

        if (!$assignment_id || !$metric_type) {
            return false;
        }

        // Use a single daily row per metric to keep tracking lightweight.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (assignment_id, event_id, season_id, metric_type, metric_date, count, created_at, updated_at)
             VALUES
                (%d, %d, %d, %s, %s, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                count = count + VALUES(count),
                updated_at = VALUES(updated_at)",
            $assignment_id,
            $event_id ? absint($event_id) : 0,
            $season_id ? absint($season_id) : 0,
            $metric_type,
            $metric_date,
            $amount,
            $now,
            $now
        ));

        return true;
    }

    public function get_metrics_summary($assignment_id) {
        global $wpdb;
        $table = $this->table('metrics');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT metric_type, SUM(count) AS total
             FROM {$table}
             WHERE assignment_id = %d
             GROUP BY metric_type
             ORDER BY metric_type ASC",
            absint($assignment_id)
        ));
    }

    public function sanitize_enum($value, $allowed, $default) {
        $value = sanitize_key((string) $value);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function sanitize_money($value) {
        $value = is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;
        return round((float) $value, 2);
    }

    public function bust_display_cache() {
        $version = (int) get_option('vms_sponsorships_display_cache_version', 1);
        update_option('vms_sponsorships_display_cache_version', $version + 1, false);
        $this->request_cache = array();
    }

    private function display_cache_key($type, $parts = array()) {
        $version = (int) get_option('vms_sponsorships_display_cache_version', 1);
        $suffix = implode('_', array_map('sanitize_key', array_map('strval', $parts)));

        return trim('vms_sponsor_' . sanitize_key($type) . '_' . $suffix . '_v' . $version, '_');
    }
}
