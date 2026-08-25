<?php

if (!defined('ABSPATH')) {
    exit;
}

final class VMS_Sponsorships {
    private static $instance = null;

    /** @var VMS_Sponsorships_Repository */
    public $repo;

    /** @var VMS_Sponsorships_Notifications */
    public $notifications;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        VMS_Sponsorships_Install::maybe_upgrade();

        $this->repo = new VMS_Sponsorships_Repository();
        $this->notifications = new VMS_Sponsorships_Notifications($this->repo);
        $public_renderer = new VMS_Sponsorships_Public_Renderer($this->repo);

        add_action('init', array($this, 'register_vendor_subtype'));
        add_action('init', array($this, 'register_assets'));

        if (is_admin()) {
            new VMS_Sponsorships_Admin($this->repo, $this->notifications);
        }

        new VMS_Sponsorships_Shortcodes($this->repo, $public_renderer);
        new VMS_Sponsorships_Public_Forms($this->repo, $this->notifications);
        new VMS_Sponsorships_Event_Plans($this->repo);
    }

    public function register_assets() {
        wp_register_style(
            'vms-sponsorships-public',
            VMS_SPONSORSHIPS_URL . 'assets/css/vms-sponsorships.css',
            array(),
            VMS_SPONSORSHIPS_VERSION
        );
    }

    /**
     * VMS Core can listen for this action to register Sponsor as a vendor subtype.
     * If no VMS Core hook exists yet, this is harmless and keeps the add-on decoupled.
     */
    public function register_vendor_subtype() {
        do_action('vms_register_vendor_type', array(
            'key'         => 'sponsor',
            'label'       => __('Sponsor', 'vms-sponsorships'),
            'description' => __('Business, individual, or organization sponsoring events, seasons, or venue placements.', 'vms-sponsorships'),
            'portal'      => 'sponsor',
        ));
    }
}
