<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Install {
    public static function activate() {
        self::create_tables();
        self::ensure_defaults();
        update_option('vms_sponsorships_version', VMS_SPONSORSHIPS_VERSION);
    }

    public static function deactivate() {
        // Intentionally preserve data on deactivation.
    }

    public static function maybe_upgrade() {
        $installed_version = (string) get_option('vms_sponsorships_version', '');
        if ($installed_version === VMS_SPONSORSHIPS_VERSION) {
            return;
        }

        self::create_tables();
        self::ensure_defaults();
        update_option('vms_sponsorships_version', VMS_SPONSORSHIPS_VERSION);
    }

    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = array();

        $sql[] = "CREATE TABLE {$prefix}vms_sponsorship_packages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            scope VARCHAR(40) NOT NULL DEFAULT 'event',
            base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 1,
            includes_physical_banner TINYINT(1) NOT NULL DEFAULT 0,
            counts_toward_banner_cap TINYINT(1) NOT NULL DEFAULT 0,
            requires_approval TINYINT(1) NOT NULL DEFAULT 1,
            public_display_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_display_enabled TINYINT(1) NOT NULL DEFAULT 1,
            report_included TINYINT(1) NOT NULL DEFAULT 1,
            required_assets TEXT NULL,
            fulfillment_template LONGTEXT NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY active (active),
            KEY scope (scope)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$prefix}vms_sponsorship_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            event_id BIGINT UNSIGNED NULL,
            season_id BIGINT UNSIGNED NULL,
            requested_package_id BIGINT UNSIGNED NULL,
            requested_slot_key VARCHAR(80) NULL,
            consideration_type VARCHAR(40) NOT NULL DEFAULT 'not_sure',
            trade_offer_details LONGTEXT NULL,
            estimated_trade_value DECIMAL(12,2) NULL,
            scope VARCHAR(40) NOT NULL DEFAULT 'event',
            status VARCHAR(40) NOT NULL DEFAULT 'submitted',
            business_name VARCHAR(190) NOT NULL,
            contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(80) NULL,
            website_url VARCHAR(255) NULL,
            business_category VARCHAR(190) NULL,
            sponsor_message LONGTEXT NULL,
            decline_reason_private LONGTEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            submitted_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY user_id (user_id),
            KEY event_id (event_id),
            KEY season_id (season_id),
            KEY requested_package_id (requested_package_id),
            KEY requested_slot_key (requested_slot_key),
            KEY consideration_type (consideration_type),
            KEY status (status),
            KEY scope (scope),
            KEY email (email)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$prefix}vms_sponsorship_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            application_id BIGINT UNSIGNED NULL,
            event_id BIGINT UNSIGNED NULL,
            season_id BIGINT UNSIGNED NULL,
            package_id BIGINT UNSIGNED NULL,
            assignment_scope VARCHAR(40) NOT NULL DEFAULT 'event',
            scope_label VARCHAR(190) NULL,
            slot_key VARCHAR(80) NOT NULL DEFAULT 'presenting',
            status VARCHAR(40) NOT NULL DEFAULT 'prospect',
            sponsor_display_name VARCHAR(190) NOT NULL,
            sponsor_tagline VARCHAR(255) NULL,
            sponsor_url VARCHAR(255) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            in_kind_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            payment_status VARCHAR(40) NOT NULL DEFAULT 'unpaid',
            public_display_enabled TINYINT(1) NOT NULL DEFAULT 1,
            placeholder_enabled TINYINT(1) NOT NULL DEFAULT 1,
            physical_banner_included TINYINT(1) NOT NULL DEFAULT 0,
            banner_slot VARCHAR(80) NULL,
            asset_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            fulfillment_status VARCHAR(40) NOT NULL DEFAULT 'not_started',
            report_status VARCHAR(40) NOT NULL DEFAULT 'not_ready',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            internal_notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY user_id (user_id),
            KEY application_id (application_id),
            KEY event_id (event_id),
            KEY season_id (season_id),
            KEY package_id (package_id),
            KEY assignment_scope (assignment_scope),
            KEY slot_key (slot_key),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY public_display_enabled (public_display_enabled),
            KEY physical_banner_included (physical_banner_included)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$prefix}vms_sponsor_assets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            assignment_id BIGINT UNSIGNED NULL,
            application_id BIGINT UNSIGNED NULL,
            asset_type VARCHAR(60) NOT NULL DEFAULT 'logo',
            attachment_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending_review',
            reviewed_by BIGINT UNSIGNED NULL,
            rejection_note LONGTEXT NULL,
            uploaded_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY user_id (user_id),
            KEY assignment_id (assignment_id),
            KEY application_id (application_id),
            KEY asset_type (asset_type),
            KEY status (status),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$prefix}vms_sponsor_metrics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NULL,
            season_id BIGINT UNSIGNED NULL,
            metric_type VARCHAR(80) NOT NULL,
            metric_date DATE NOT NULL,
            count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY metric_rollup (assignment_id, metric_type, metric_date),
            KEY event_id (event_id),
            KEY season_id (season_id),
            KEY metric_type (metric_type),
            KEY metric_date (metric_date)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$prefix}vms_sponsor_fulfillment_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id BIGINT UNSIGNED NOT NULL,
            item_key VARCHAR(120) NOT NULL,
            label VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'not_started',
            due_at DATETIME NULL,
            completed_at DATETIME NULL,
            completed_by BIGINT UNSIGNED NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY assignment_id (assignment_id),
            KEY item_key (item_key),
            KEY status (status),
            KEY due_at (due_at)
        ) $charset_collate;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }

    public static function default_package_definitions() {
        return array(
            'presenting-sponsor' => array(
                'name' => __('Presenting Sponsor', 'vms-sponsorships'),
                'slug' => 'presenting-sponsor',
                'scope' => 'event',
                'base_price' => 500,
                'includes_physical_banner' => 1,
                'counts_toward_banner_cap' => 1,
                'description' => __('Premier event sponsorship with featured recognition before, during, and after the show.', 'vms-sponsorships'),
                'legacy_descriptions' => array(
                    '',
                    'Primary event sponsorship with premium public recognition and one controlled physical banner placement.',
                ),
                'required_assets' => array('logo', 'web_banner'),
                'fulfillment_template' => array(
                    array('key' => 'event_page_logo', 'label' => __('Logo added to event page', 'vms-sponsorships')),
                    array('key' => 'email_mention', 'label' => __('Sponsor included in event email/newsletter', 'vms-sponsorships')),
                    array('key' => 'onsite_banner', 'label' => __('Approved banner/signage placed on site', 'vms-sponsorships')),
                    array('key' => 'report_delivered', 'label' => __('Sponsor report delivered', 'vms-sponsorships')),
                ),
                'benefits' => array(
                    __('Featured placement on the event page', 'vms-sponsorships'),
                    __('Eligible for newsletter/email recognition', 'vms-sponsorships'),
                    __('Sponsor mention in event-related promotion', 'vms-sponsorships'),
                    __('Limited physical banner opportunity, subject to approval', 'vms-sponsorships'),
                    __('Post-event visibility summary', 'vms-sponsorships'),
                ),
                'consideration_type' => 'cash',
                'display_priority' => 10,
            ),
            'supporting-sponsor' => array(
                'name' => __('Supporting Sponsor', 'vms-sponsorships'),
                'slug' => 'supporting-sponsor',
                'scope' => 'event',
                'base_price' => 150,
                'includes_physical_banner' => 0,
                'counts_toward_banner_cap' => 0,
                'description' => __('A simple way to put your business in front of Serenade Range guests through event and sponsor recognition.', 'vms-sponsorships'),
                'legacy_descriptions' => array(
                    '',
                    'A simple digital sponsorship option for businesses that want to support live music without physical signage.',
                    'Tasteful digital recognition without physical banner placement.',
                ),
                'required_assets' => array('logo'),
                'fulfillment_template' => array(
                    array('key' => 'event_page_logo', 'label' => __('Logo added to event page', 'vms-sponsorships')),
                    array('key' => 'report_delivered', 'label' => __('Sponsor report delivered', 'vms-sponsorships')),
                ),
                'benefits' => array(
                    __('Public event-page recognition', 'vms-sponsorships'),
                    __('Eligible for email/newsletter placement', 'vms-sponsorships'),
                    __('Post-event visibility summary', 'vms-sponsorships'),
                    __('Straightforward sponsor recognition for businesses that want a general visibility package', 'vms-sponsorships'),
                ),
                'consideration_type' => 'cash',
                'display_priority' => 20,
            ),
            'kids-admission-sponsor' => array(
                'name' => __('Kids Admission Sponsor', 'vms-sponsorships'),
                'slug' => 'kids-admission-sponsor',
                'scope' => 'feature',
                'base_price' => 250,
                'includes_physical_banner' => 0,
                'counts_toward_banner_cap' => 0,
                'description' => __('Help keep Serenade Range family-friendly by supporting accessible admission for kids and families.', 'vms-sponsorships'),
                'legacy_descriptions' => array(
                    '',
                    'Helps keep kids and family admission accessible while connecting your business to a welcoming part of the event experience.',
                    'Supports free or discounted kids admission recognition.',
                ),
                'required_assets' => array('logo'),
                'fulfillment_template' => array(
                    array('key' => 'event_page_logo', 'label' => __('Logo added to event page', 'vms-sponsorships')),
                    array('key' => 'admission_language', 'label' => __('Kids admission sponsorship language included', 'vms-sponsorships')),
                    array('key' => 'report_delivered', 'label' => __('Sponsor report delivered', 'vms-sponsorships')),
                ),
                'benefits' => array(
                    __('Recognition tied to kids and family admission messaging', 'vms-sponsorships'),
                    __('Public event-page recognition', 'vms-sponsorships'),
                    __('Eligible for email/newsletter recognition', 'vms-sponsorships'),
                    __('May be included in sponsor promotion depending on the event plan', 'vms-sponsorships'),
                    __('Post-event visibility summary', 'vms-sponsorships'),
                ),
                'consideration_type' => 'cash',
                'display_priority' => 30,
            ),
            'veterans-admission-sponsor' => array(
                'name' => __('Veterans Admission Sponsor', 'vms-sponsorships'),
                'slug' => 'veterans-admission-sponsor',
                'scope' => 'feature',
                'base_price' => 250,
                'includes_physical_banner' => 0,
                'counts_toward_banner_cap' => 0,
                'description' => __('Help us welcome veterans and active service members with free or reduced admission opportunities.', 'vms-sponsorships'),
                'legacy_descriptions' => array(
                    '',
                    'Helps support free or reduced admission for veterans and active service members while associating your business with community appreciation.',
                    'Supports free or discounted veterans admission recognition.',
                ),
                'required_assets' => array('logo'),
                'fulfillment_template' => array(
                    array('key' => 'event_page_logo', 'label' => __('Logo added to event page', 'vms-sponsorships')),
                    array('key' => 'admission_language', 'label' => __('Veterans admission sponsorship language included', 'vms-sponsorships')),
                    array('key' => 'report_delivered', 'label' => __('Sponsor report delivered', 'vms-sponsorships')),
                ),
                'benefits' => array(
                    __('Recognition tied to veterans and service-member admission messaging', 'vms-sponsorships'),
                    __('Public event-page recognition', 'vms-sponsorships'),
                    __('Eligible for email/newsletter recognition', 'vms-sponsorships'),
                    __('May be included in sponsor promotion depending on the event plan', 'vms-sponsorships'),
                    __('Post-event visibility summary', 'vms-sponsorships'),
                ),
                'consideration_type' => 'cash',
                'display_priority' => 40,
            ),
            'in-kind-custom-sponsorship' => array(
                'name' => __('In-Kind / Custom Sponsorship', 'vms-sponsorships'),
                'slug' => 'in-kind-custom-sponsorship',
                'scope' => 'event',
                'base_price' => 0,
                'includes_physical_banner' => 0,
                'counts_toward_banner_cap' => 0,
                'description' => __('Support Serenade Range with food or beverage support, raffle or giveaway items, printing or signage, or equipment and service-based contributions. Every custom offer is reviewed before recognition is confirmed.', 'vms-sponsorships'),
                'legacy_descriptions' => array(
                    '',
                    'Offer services, goods, or a custom support package that helps the show or venue run smoothly. Each custom sponsorship is reviewed before recognition or benefits are confirmed.',
                ),
                'required_assets' => array(),
                'fulfillment_template' => array(
                    array('key' => 'custom_scope_confirmed', 'label' => __('Custom deliverables reviewed and confirmed', 'vms-sponsorships')),
                    array('key' => 'recognition_confirmed', 'label' => __('Recognition plan approved', 'vms-sponsorships')),
                    array('key' => 'report_delivered', 'label' => __('Sponsor report delivered', 'vms-sponsorships')),
                ),
                'benefits' => array(
                    __('Good fit for donated services, goods, labor, or custom support ideas', 'vms-sponsorships'),
                    __('Recognition can be tailored after review and approval', 'vms-sponsorships'),
                    __('We follow up to confirm value, fit, and deliverables', 'vms-sponsorships'),
                    __('No payment is collected when you apply', 'vms-sponsorships'),
                ),
                'consideration_type' => 'trade_in_kind',
                'display_priority' => 50,
            ),
        );
    }

    public static function default_package_profile($slug) {
        $definitions = self::default_package_definitions();
        $slug = sanitize_title($slug);

        if (empty($definitions[$slug])) {
            return null;
        }

        return array(
            'description' => $definitions[$slug]['description'],
            'legacy_descriptions' => $definitions[$slug]['legacy_descriptions'],
            'benefits' => $definitions[$slug]['benefits'],
            'consideration_type' => $definitions[$slug]['consideration_type'],
            'display_priority' => (int) $definitions[$slug]['display_priority'],
        );
    }

    public static function default_visibility_settings() {
        return array(
            'vms_sponsorships_visibility_title' => __('Why Sponsor Serenade Range?', 'vms-sponsorships'),
            'vms_sponsorships_visibility_intro' => __('Sponsors can be seen through event pages, onsite signage, email/newsletter mentions, and social promotion, depending on package.', 'vms-sponsorships'),
            'vms_sponsorships_visibility_website' => __('Featured event pages, sponsor landing pages, and venue marketing touchpoints.', 'vms-sponsorships'),
            'vms_sponsorships_visibility_facebook' => '',
            'vms_sponsorships_visibility_instagram' => '',
            'vms_sponsorships_visibility_email' => '',
            'vms_sponsorships_visibility_attendance' => __('Local East Texas live music fans, families, and community supporters across the event calendar.', 'vms-sponsorships'),
            'vms_sponsorships_visibility_custom_note' => '',
            'vms_sponsorships_visibility_internal_notes' => '',
            'vms_sponsorships_visibility_last_updated' => '',
            'vms_sponsorships_visibility_last_saved_at' => 0,
        );
    }

    public static function default_unsold_banner_settings() {
        return array(
            'vms_sponsorships_banner_headline' => __('This event\'s headline sponsor spot is open', 'vms-sponsorships'),
            'vms_sponsorships_banner_body' => __('Put your brand in front of East Texas music fans with a high-visibility event-page placement before, during, and after the show.', 'vms-sponsorships'),
            'vms_sponsorships_banner_cta_text' => __('Claim this sponsor placement', 'vms-sponsorships'),
            'vms_sponsorships_banner_cta_url' => '',
            'vms_sponsorships_banner_desktop_image_id' => 0,
            'vms_sponsorships_banner_mobile_image_id' => 0,
            'vms_sponsorships_banner_image_id' => 0,
            'vms_sponsorships_event_page_placement_mode' => VMS_Sponsorships_Public_Renderer::EVENT_PAGE_PLACEMENT_MANUAL,
        );
    }

    private static function ensure_defaults() {
        self::ensure_default_packages();
        self::ensure_default_options();
        self::maybe_refresh_default_package_copy();
        self::maybe_refresh_default_unsold_banner_copy();

        if (false === get_option('vms_sponsorships_display_cache_version', false)) {
            add_option('vms_sponsorships_display_cache_version', 1, '', false);
        }
    }

    private static function ensure_default_packages() {
        global $wpdb;

        $table = $wpdb->prefix . 'vms_sponsorship_packages';
        $now = current_time('mysql');

        foreach (self::default_package_definitions() as $definition) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                $definition['slug']
            ));

            if ($existing_id) {
                continue;
            }

            $wpdb->insert($table, array(
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'scope' => $definition['scope'],
                'base_price' => $definition['base_price'],
                'active' => 1,
                'includes_physical_banner' => $definition['includes_physical_banner'],
                'counts_toward_banner_cap' => $definition['counts_toward_banner_cap'],
                'requires_approval' => 1,
                'public_display_enabled' => 1,
                'email_display_enabled' => 1,
                'report_included' => 1,
                'required_assets' => wp_json_encode($definition['required_assets']),
                'fulfillment_template' => wp_json_encode($definition['fulfillment_template']),
                'description' => $definition['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        add_option('vms_sponsorships_seeded_defaults', 1);
    }

    private static function ensure_default_options() {
        $defaults = array(
            'vms_sponsorships_max_event_banners' => 1,
            'vms_sponsorships_placeholder_heading' => __('Your business could sponsor this show', 'vms-sponsorships'),
            'vms_sponsorships_placeholder_body' => __('Put your brand in front of East Texas music fans before, during, and after the event.', 'vms-sponsorships'),
            'vms_sponsorships_placeholder_button' => __('Sponsor this event', 'vms-sponsorships'),
            'vms_sponsorships_inquiry_page_url' => '',
        );

        foreach ($defaults as $option_name => $value) {
            if (false === get_option($option_name, false)) {
                add_option($option_name, $value);
            }
        }

        foreach (self::default_visibility_settings() as $option_name => $value) {
            if (false === get_option($option_name, false)) {
                add_option($option_name, $value);
            }
        }

        foreach (self::default_unsold_banner_settings() as $option_name => $value) {
            if (false === get_option($option_name, false)) {
                add_option($option_name, $value);
            }
        }

        self::maybe_migrate_legacy_banner_image_option();
    }

    private static function maybe_migrate_legacy_banner_image_option() {
        $legacy_image_id = absint(get_option('vms_sponsorships_banner_image_id', 0));
        if ($legacy_image_id <= 0) {
            return;
        }

        $desktop_image_id = absint(get_option('vms_sponsorships_banner_desktop_image_id', 0));
        if ($desktop_image_id <= 0) {
            update_option('vms_sponsorships_banner_desktop_image_id', $legacy_image_id);
        }
    }

    private static function maybe_refresh_default_package_copy() {
        global $wpdb;

        $table = $wpdb->prefix . 'vms_sponsorship_packages';
        $now = current_time('mysql');

        foreach (self::default_package_definitions() as $slug => $definition) {
            $package = $wpdb->get_row($wpdb->prepare(
                "SELECT id, description FROM {$table} WHERE slug = %s LIMIT 1",
                $slug
            ));

            if (!$package) {
                continue;
            }

            $description = trim(wp_strip_all_tags((string) $package->description));
            if (in_array($description, $definition['legacy_descriptions'], true)) {
                $wpdb->update($table, array(
                    'description' => $definition['description'],
                    'updated_at' => $now,
                ), array('id' => (int) $package->id));
            }
        }
    }

    private static function maybe_refresh_default_unsold_banner_copy() {
        $current_body = (string) get_option('vms_sponsorships_banner_body', '');
        $legacy_default_body = __('Put your brand in front of East Texas music fans with a high-visibility event-page placement that reads like a real sponsor campaign, not a leftover placeholder.', 'vms-sponsorships');
        $default_settings = self::default_unsold_banner_settings();
        $refreshed_default_body = (string) $default_settings['vms_sponsorships_banner_body'];

        if ($current_body === $legacy_default_body) {
            update_option('vms_sponsorships_banner_body', $refreshed_default_body);
        }
    }
}
