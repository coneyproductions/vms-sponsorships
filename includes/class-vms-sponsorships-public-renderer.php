<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Public_Renderer {
    public const EVENT_PAGE_PLACEMENT_DISABLED = 'disabled';
    public const EVENT_PAGE_PLACEMENT_MANUAL = 'manual_shortcode';
    public const EVENT_PAGE_PLACEMENT_AUTOMATIC = 'automatic';

    private const LAYOUT_BANNER = 'banner';
    private const LAYOUT_CARD = 'card';

    /** @var VMS_Sponsorships_Repository */
    private $repo;

    public function __construct(VMS_Sponsorships_Repository $repo) {
        $this->repo = $repo;
    }

    public function render_event_slot($event_id, $slot, $args = array()) {
        $event_id = absint($event_id);
        $slot = sanitize_key($slot);

        if ($event_id <= 0 || $slot === '') {
            return '';
        }

        $args = wp_parse_args($args, array(
            'email_safe' => false,
            'layout' => self::LAYOUT_CARD,
            'scope' => 'event',
            'show_placeholder' => true,
        ));

        $email_safe = !empty($args['email_safe']);
        $layout = $this->sanitize_layout($args['layout'] ?? self::LAYOUT_CARD, $email_safe);
        $show_placeholder = !empty($args['show_placeholder']);
        $scope = sanitize_key($args['scope'] ?? 'event');

        if ($this->should_skip_standard_event_slot_render($event_id, $slot, $layout)) {
            return '';
        }

        if ($this->is_duplicate_event_page_banner_render($event_id, $slot, $layout)) {
            return '';
        }

        $assignment = $this->repo->get_public_assignment($event_id, $slot);
        if ($assignment) {
            $this->maybe_track_impression($assignment, $email_safe);
            $markup = $this->render_assignment_record($assignment, array(
                'email_safe' => $email_safe,
                'layout' => $layout,
            ));
        } else {
            $markup = $show_placeholder
                ? $this->render_placeholder_slot($event_id, $slot, array(
                    'email_safe' => $email_safe,
                    'layout' => $layout,
                    'scope' => $scope,
                ))
                : '';
        }

        if ($markup !== '') {
            $this->mark_event_page_banner_rendered($event_id, $slot, $layout);
        }

        return $markup;
    }

    public function render_placeholder_slot($event_id, $slot, $args = array()) {
        $event_id = absint($event_id);
        $slot = sanitize_key($slot);

        if ($slot === '') {
            return '';
        }

        $args = wp_parse_args($args, array(
            'email_safe' => false,
            'layout' => self::LAYOUT_CARD,
            'scope' => 'event',
        ));

        $email_safe = !empty($args['email_safe']);
        $layout = $this->sanitize_layout($args['layout'] ?? self::LAYOUT_CARD, $email_safe);
        $scope = sanitize_key($args['scope'] ?? 'event');

        if ($event_id > 0 && $this->should_skip_standard_event_slot_render($event_id, $slot, $layout)) {
            return '';
        }

        if ($event_id > 0 && $this->is_duplicate_event_page_banner_render($event_id, $slot, $layout)) {
            return '';
        }

        if (!$email_safe) {
            wp_enqueue_style('vms-sponsorships-public');
        }

        $markup = $layout === self::LAYOUT_BANNER
            ? $this->render_banner_placeholder($event_id, $slot, $scope)
            : $this->render_card_placeholder($event_id, $slot, $scope, $email_safe);

        if ($markup !== '' && $event_id > 0) {
            $this->mark_event_page_banner_rendered($event_id, $slot, $layout);
        }

        return $markup;
    }

    public function render_assignment_record($assignment, $args = array()) {
        if (!is_object($assignment) || empty($assignment->id)) {
            return '';
        }

        $args = wp_parse_args($args, array(
            'email_safe' => false,
            'layout' => self::LAYOUT_CARD,
        ));

        $email_safe = !empty($args['email_safe']);
        $layout = $this->sanitize_layout($args['layout'] ?? self::LAYOUT_CARD, $email_safe);

        if (!$email_safe) {
            wp_enqueue_style('vms-sponsorships-public');
        }

        if ($layout === self::LAYOUT_BANNER) {
            return $this->render_banner_assignment($assignment);
        }

        return $this->render_card_assignment($assignment, $email_safe);
    }

    public function render_event_page_banner($event_id) {
        $event_id = absint($event_id);
        if ($event_id <= 0 || $this->event_page_placement_mode() !== self::EVENT_PAGE_PLACEMENT_AUTOMATIC) {
            return '';
        }

        return $this->render_event_slot($event_id, $this->event_page_banner_slot(), array(
            'email_safe' => false,
            'layout' => self::LAYOUT_BANNER,
            'scope' => 'event',
            'show_placeholder' => true,
        ));
    }

    public function event_page_placement_mode() {
        $mode = get_option('vms_sponsorships_event_page_placement_mode', self::EVENT_PAGE_PLACEMENT_MANUAL);
        return $this->sanitize_placement_mode($mode);
    }

    public function event_page_banner_slot() {
        $slot = sanitize_key((string) apply_filters('vms_sponsorships_event_page_banner_slot', 'presenting'));
        return $slot !== '' ? $slot : 'presenting';
    }

    private function render_card_assignment($assignment, $email_safe = false) {
        $logo_url = $this->repo->get_approved_logo_url($assignment->id);
        $has_link = !empty($assignment->sponsor_url);
        $link_url = $has_link ? $this->assignment_link($assignment, $email_safe) : '';
        $classes = $email_safe ? 'vms-sponsor-block vms-sponsor-block--email' : 'vms-sponsor-block';

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

    private function render_card_placeholder($event_id, $slot, $scope, $email_safe = false) {
        $heading = get_option('vms_sponsorships_placeholder_heading', 'Your business could sponsor this show');
        $body = get_option('vms_sponsorships_placeholder_body', 'Put your brand in front of East Texas music fans before, during, and after the event.');
        $button = get_option('vms_sponsorships_placeholder_button', 'Sponsor this event');
        $url = $this->resolve_cta_url($event_id, $slot, $scope, (string) get_option('vms_sponsorships_inquiry_page_url', ''));
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

    private function render_banner_assignment($assignment) {
        $logo_url = $this->repo->get_approved_logo_url($assignment->id);
        $has_link = !empty($assignment->sponsor_url);
        $link_url = $has_link ? $this->assignment_link($assignment, false) : '';
        $body = trim((string) ($assignment->sponsor_tagline ?? ''));
        if ($body === '') {
            $body = __('Supporting live music at Serenade Range.', 'vms-sponsorships');
        }

        ob_start();
        ?>
        <section class="vms-sponsor-banner vms-sponsor-banner--assigned" data-vms-sponsor-banner="assigned" data-vms-sponsor-slot="<?php echo esc_attr($assignment->slot_key); ?>">
            <div class="vms-sponsor-banner__content">
                <p class="vms-sponsor-banner__eyebrow"><?php esc_html_e('Sponsor Spotlight', 'vms-sponsorships'); ?></p>
                <p class="vms-sponsor-banner__slot"><?php echo esc_html($this->slot_label($assignment->slot_key)); ?></p>
                <p class="vms-sponsor-banner__title"><?php echo esc_html($assignment->sponsor_display_name); ?></p>
                <p class="vms-sponsor-banner__body"><?php echo wp_kses_post(nl2br(esc_html($body))); ?></p>
                <?php if ($has_link) : ?>
                    <a class="vms-sponsor-banner__button" href="<?php echo esc_url($link_url); ?>" target="_blank" rel="noopener noreferrer sponsored"><?php esc_html_e('Visit Sponsor', 'vms-sponsorships'); ?></a>
                <?php endif; ?>
            </div>
            <div class="vms-sponsor-banner__media">
                <div class="vms-sponsor-banner__badge"><?php esc_html_e('Paid Sponsor', 'vms-sponsorships'); ?></div>
                <?php if ($logo_url) : ?>
                    <img class="vms-sponsor-banner__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($assignment->sponsor_display_name); ?>">
                <?php else : ?>
                    <div class="vms-sponsor-banner__monogram" aria-hidden="true"><?php echo esc_html($this->monogram($assignment->sponsor_display_name)); ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php

        return trim(ob_get_clean());
    }

    private function render_banner_placeholder($event_id, $slot, $scope) {
        $headline = (string) get_option('vms_sponsorships_banner_headline', __('This event\'s headline sponsor spot is open', 'vms-sponsorships'));
        $body = (string) get_option('vms_sponsorships_banner_body', __('Put your brand in front of East Texas music fans with a high-visibility event-page placement before, during, and after the show.', 'vms-sponsorships'));
        $button = (string) get_option('vms_sponsorships_banner_cta_text', __('Claim this sponsor placement', 'vms-sponsorships'));
        $url = $this->resolve_cta_url($event_id, $slot, $scope, (string) get_option('vms_sponsorships_banner_cta_url', ''));
        $artwork = $this->resolve_unsold_banner_artwork_context();
        $has_artwork = $artwork['has_artwork'];
        $banner_classes = array(
            'vms-sponsor-banner',
            'vms-sponsor-banner--unsold',
        );

        if ($has_artwork) {
            $banner_classes[] = 'vms-sponsor-banner--artwork-' . $artwork['desktop_class_suffix'];
            if (!empty($artwork['is_mobile_wide_fallback'])) {
                $banner_classes[] = 'vms-sponsor-banner--mobile-wide-fallback';
            }
        } else {
            $banner_classes[] = 'vms-sponsor-banner--text-only';
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr(implode(' ', $banner_classes)); ?>" data-vms-sponsor-banner="unsold" data-vms-sponsor-slot="<?php echo esc_attr($slot); ?>">
            <?php if ($has_artwork && $artwork['render_media_first']) : ?>
                <?php echo $artwork['media_markup']; ?>
            <?php endif; ?>
            <div class="vms-sponsor-banner__content">
                <p class="vms-sponsor-banner__title"><?php echo esc_html($headline); ?></p>
                <p class="vms-sponsor-banner__body"><?php echo wp_kses_post(nl2br(esc_html($body))); ?></p>
                <a class="vms-sponsor-banner__button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($button); ?></a>
            </div>
            <?php if ($has_artwork && !$artwork['render_media_first']) : ?>
                <?php echo $artwork['media_markup']; ?>
            <?php endif; ?>
        </section>
        <?php

        return trim(ob_get_clean());
    }

    private function resolve_unsold_banner_artwork_context() {
        list($desktop_image_id, $mobile_image_id) = $this->unsold_banner_artwork_ids();

        $desktop_artwork = $this->banner_artwork_descriptor($desktop_image_id);
        $has_distinct_mobile_artwork = $mobile_image_id > 0 && $mobile_image_id !== $desktop_image_id;
        $mobile_artwork = $has_distinct_mobile_artwork
            ? $this->banner_artwork_descriptor($mobile_image_id)
            : $desktop_artwork;

        $has_artwork = !empty($desktop_artwork['id']);
        $desktop_class_suffix = $this->banner_artwork_class_suffix($desktop_artwork['layout'] ?? '');
        $mobile_class_suffix = $this->banner_artwork_class_suffix($mobile_artwork['layout'] ?? '');
        $artwork_markup = $has_artwork
            ? $this->render_unsold_banner_artwork($desktop_artwork, $mobile_artwork)
            : '';

        return array(
            'has_artwork' => $artwork_markup !== '',
            'desktop_artwork' => $desktop_artwork,
            'mobile_artwork' => $mobile_artwork,
            'desktop_class_suffix' => $desktop_class_suffix,
            'mobile_class_suffix' => $mobile_class_suffix,
            'render_media_first' => 'wide' === $desktop_class_suffix,
            'is_mobile_wide_fallback' => !$has_distinct_mobile_artwork && 'wide' === $desktop_class_suffix,
            'media_markup' => $artwork_markup !== '' ? $this->render_unsold_banner_media($artwork_markup) : '',
        );
    }

    private function unsold_banner_artwork_ids() {
        $legacy_image_id = absint(get_option('vms_sponsorships_banner_image_id', 0));
        $desktop_image_id = absint(get_option('vms_sponsorships_banner_desktop_image_id', $legacy_image_id));
        $mobile_image_id = absint(get_option('vms_sponsorships_banner_mobile_image_id', 0));

        if ($desktop_image_id <= 0 && $legacy_image_id > 0) {
            $desktop_image_id = $legacy_image_id;
        }

        if ($desktop_image_id <= 0 && $mobile_image_id > 0) {
            $desktop_image_id = $mobile_image_id;
        }

        return array($desktop_image_id, $mobile_image_id);
    }

    private function banner_artwork_descriptor($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return array(
                'id' => 0,
                'width' => 0,
                'height' => 0,
                'aspect_ratio' => 0.0,
                'layout' => '',
            );
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $width = absint($metadata['width'] ?? 0);
        $height = absint($metadata['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            $image_src = wp_get_attachment_image_src($attachment_id, 'full');
            if (is_array($image_src)) {
                $width = absint($image_src[1] ?? 0);
                $height = absint($image_src[2] ?? 0);
            }
        }

        $aspect_ratio = $width > 0 && $height > 0
            ? round($width / max(1, $height), 3)
            : 1.0;

        return array(
            'id' => $attachment_id,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $aspect_ratio,
            'layout' => $this->classify_banner_artwork_layout($aspect_ratio),
        );
    }

    private function classify_banner_artwork_layout($aspect_ratio) {
        $aspect_ratio = (float) $aspect_ratio;

        if ($aspect_ratio >= 2.0) {
            return 'wide_banner';
        }

        if ($aspect_ratio < 0.8) {
            return 'portrait_mobile';
        }

        return 'square_or_standard';
    }

    private function banner_artwork_class_suffix($layout) {
        switch ((string) $layout) {
            case 'wide_banner':
                return 'wide';
            case 'portrait_mobile':
                return 'portrait';
            case 'square_or_standard':
            default:
                return 'standard';
        }
    }

    private function render_unsold_banner_media($artwork_markup) {
        ob_start();
        ?>
        <div class="vms-sponsor-banner__media">
            <?php echo $artwork_markup; ?>
        </div>
        <?php

        return trim(ob_get_clean());
    }

    private function render_unsold_banner_artwork($desktop_artwork, $mobile_artwork) {
        $desktop_image_id = absint($desktop_artwork['id'] ?? 0);
        $mobile_image_id = absint($mobile_artwork['id'] ?? 0);

        if ($desktop_image_id <= 0) {
            return '';
        }

        $desktop_layout = (string) ($desktop_artwork['layout'] ?? 'square_or_standard');
        $mobile_layout = (string) ($mobile_artwork['layout'] ?? $desktop_layout);
        $desktop_markup = $this->render_unsold_banner_artwork_variant($desktop_artwork, 'desktop', $desktop_layout);

        if ($desktop_markup === '') {
            return '';
        }

        $artwork_markup = $desktop_markup;
        $artwork_classes = array(
            'vms-sponsor-banner__artwork',
            'vms-sponsor-banner__artwork--desktop-' . $this->banner_artwork_class_suffix($desktop_layout),
            'vms-sponsor-banner__artwork--mobile-' . $this->banner_artwork_class_suffix($mobile_layout),
        );

        if ($mobile_image_id > 0 && $mobile_image_id !== $desktop_image_id) {
            $mobile_markup = $this->render_unsold_banner_artwork_variant($mobile_artwork, 'mobile', $mobile_layout);
            if ($mobile_markup !== '') {
                $artwork_classes[] = 'vms-sponsor-banner__artwork--has-mobile-variant';
                $artwork_markup .= $mobile_markup;
            }
        }

        return sprintf(
            '<div class="%1$s" data-vms-artwork-layout="%2$s" data-vms-artwork-mobile-layout="%3$s" data-vms-artwork-width="%4$d" data-vms-artwork-height="%5$d" data-vms-artwork-aspect="%6$s">%7$s</div>',
            esc_attr(implode(' ', $artwork_classes)),
            esc_attr($desktop_layout),
            esc_attr($mobile_layout),
            absint($desktop_artwork['width'] ?? 0),
            absint($desktop_artwork['height'] ?? 0),
            esc_attr((string) ($desktop_artwork['aspect_ratio'] ?? '')),
            $artwork_markup
        );
    }

    private function render_unsold_banner_artwork_variant($artwork, $variant, $layout) {
        $attachment_id = absint($artwork['id'] ?? 0);
        if ($attachment_id <= 0) {
            return '';
        }

        $variant = sanitize_key((string) $variant);
        $layout = (string) $layout;
        $image_markup = wp_get_attachment_image($attachment_id, $this->banner_artwork_image_size($layout), false, array(
            'class' => 'vms-sponsor-banner__image vms-sponsor-banner__image--' . $variant,
            'alt' => '',
            'loading' => 'lazy',
            'decoding' => 'async',
            'sizes' => $this->banner_artwork_sizes($layout),
        ));

        if ($image_markup === '') {
            return '';
        }

        return sprintf(
            '<div class="%1$s" data-vms-artwork-variant="%2$s" data-vms-artwork-layout="%3$s" aria-hidden="true">%4$s</div>',
            esc_attr(implode(' ', array(
                'vms-sponsor-banner__artwork-variant',
                'vms-sponsor-banner__artwork-variant--' . $variant,
                'vms-sponsor-banner__artwork-variant--' . $variant . '-' . $this->banner_artwork_class_suffix($layout),
            ))),
            esc_attr($variant),
            esc_attr($layout),
            $image_markup
        );
    }

    private function banner_artwork_image_size($layout) {
        return 'wide_banner' === (string) $layout ? 'full' : 'large';
    }

    private function banner_artwork_sizes($layout) {
        switch ((string) $layout) {
            case 'wide_banner':
                return '(max-width: 782px) 100vw, 92vw';
            case 'portrait_mobile':
                return '(max-width: 782px) 100vw, (max-width: 1200px) 28vw, 320px';
            case 'square_or_standard':
            default:
                return '(max-width: 782px) 100vw, (max-width: 1200px) 38vw, 420px';
        }
    }

    private function resolve_cta_url($event_id, $slot, $scope, $preferred_url = '') {
        $preferred_url = esc_url_raw($preferred_url);
        $base_url = $preferred_url !== '' ? $preferred_url : (string) get_option('vms_sponsorships_inquiry_page_url', '');

        if ($base_url === '') {
            return $this->built_in_application_url($event_id, $slot, $scope);
        }

        return add_query_arg(array(
            'vms_sponsor_event_id' => absint($event_id),
            'vms_sponsor_slot' => sanitize_key($slot),
            'vms_sponsor_scope' => sanitize_key($scope),
        ), $base_url);
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

        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        $transient_key = 'vms_sponsor_impression_' . absint($assignment->id) . '_' . wp_hash($remote_addr) . '_' . gmdate('YmdH');
        if (get_transient($transient_key)) {
            return;
        }

        set_transient($transient_key, 1, HOUR_IN_SECONDS);
        $this->repo->increment_metric($assignment->id, 'sponsor_impression', $assignment->event_id, $assignment->season_id);
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

    private function sanitize_layout($layout, $email_safe = false) {
        if ($email_safe) {
            return self::LAYOUT_CARD;
        }

        $layout = sanitize_key((string) $layout);
        return in_array($layout, array(self::LAYOUT_CARD, self::LAYOUT_BANNER), true) ? $layout : self::LAYOUT_CARD;
    }

    private function sanitize_placement_mode($mode) {
        $mode = sanitize_key((string) $mode);
        $allowed = array(
            self::EVENT_PAGE_PLACEMENT_DISABLED,
            self::EVENT_PAGE_PLACEMENT_MANUAL,
            self::EVENT_PAGE_PLACEMENT_AUTOMATIC,
        );

        return in_array($mode, $allowed, true) ? $mode : self::EVENT_PAGE_PLACEMENT_MANUAL;
    }

    private function should_skip_standard_event_slot_render($event_id, $slot, $layout) {
        return $layout !== self::LAYOUT_BANNER
            && $this->event_page_placement_mode() === self::EVENT_PAGE_PLACEMENT_AUTOMATIC
            && $this->is_current_event_page_slot($event_id, $slot);
    }

    private function is_duplicate_event_page_banner_render($event_id, $slot, $layout) {
        if ($layout !== self::LAYOUT_BANNER || !$this->is_current_event_page_slot($event_id, $slot)) {
            return false;
        }

        $key = $this->event_page_render_key($event_id, $slot);
        $rendered = isset($GLOBALS['vms_sponsorships_event_page_banner_rendered']) && is_array($GLOBALS['vms_sponsorships_event_page_banner_rendered'])
            ? $GLOBALS['vms_sponsorships_event_page_banner_rendered']
            : array();

        return !empty($rendered[$key]);
    }

    private function mark_event_page_banner_rendered($event_id, $slot, $layout) {
        if ($layout !== self::LAYOUT_BANNER || !$this->is_current_event_page_slot($event_id, $slot)) {
            return;
        }

        if (!isset($GLOBALS['vms_sponsorships_event_page_banner_rendered']) || !is_array($GLOBALS['vms_sponsorships_event_page_banner_rendered'])) {
            $GLOBALS['vms_sponsorships_event_page_banner_rendered'] = array();
        }

        $GLOBALS['vms_sponsorships_event_page_banner_rendered'][$this->event_page_render_key($event_id, $slot)] = true;
    }

    private function is_current_event_page_slot($event_id, $slot) {
        if (is_admin() || !function_exists('is_singular') || !is_singular('tribe_events')) {
            return false;
        }

        $queried_id = function_exists('get_queried_object_id') ? absint(get_queried_object_id()) : 0;
        return $queried_id > 0
            && $queried_id === absint($event_id)
            && sanitize_key($slot) === $this->event_page_banner_slot();
    }

    private function event_page_render_key($event_id, $slot) {
        return absint($event_id) . ':' . sanitize_key($slot);
    }

    private function monogram($name) {
        $words = preg_split('/\s+/', trim((string) $name));
        if (!is_array($words) || empty($words)) {
            return 'SR';
        }

        $letters = '';
        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }

            $letters .= strtoupper(substr($word, 0, 1));
            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'SR';
    }

    public function slot_label($slot) {
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
