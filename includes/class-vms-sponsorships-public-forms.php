<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Public_Forms {
    private const APPLICATION_DUPLICATE_WINDOW = 10 * MINUTE_IN_SECONDS;
    private const ASSET_DUPLICATE_WINDOW = 2 * MINUTE_IN_SECONDS;
    private const HONEYPOT_FIELD = 'vms_sponsorships_website_hp';

    /** @var VMS_Sponsorships_Repository */
    private $repo;

    /** @var VMS_Sponsorships_Notifications */
    private $notifications;

    public function __construct(VMS_Sponsorships_Repository $repo, VMS_Sponsorships_Notifications $notifications) {
        $this->repo = $repo;
        $this->notifications = $notifications;

        add_shortcode('vms_sponsor_inquiry', array($this, 'inquiry_page_shortcode'));
        add_shortcode('vms_sponsor_apply', array($this, 'application_form_shortcode'));
        add_shortcode('vms_sponsor_asset_upload', array($this, 'asset_upload_shortcode'));
        add_action('init', array($this, 'handle_public_posts'));
        add_action('template_redirect', array($this, 'maybe_render_built_in_application_page'));
    }

    public function handle_public_posts() {
        $request = wp_unslash($_POST);
        if (empty($request['vms_sponsorships_public_action'])) {
            return;
        }

        $action = sanitize_key($request['vms_sponsorships_public_action']);

        if ('submit_application' === $action) {
            $this->handle_application_submission();
        }

        if ('upload_asset' === $action) {
            $this->handle_asset_upload();
        }
    }

    private function handle_application_submission() {
        $request = wp_unslash($_POST);
        $nonce = sanitize_text_field($request['_wpnonce'] ?? '');

        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_sponsorships_submit_application')) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'nonce'));
        }

        if ($this->is_honeypot_tripped($request)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'spam'));
        }

        if ($this->has_recent_application_submission($request)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'duplicate_submission'));
        }

        $requested_package_id = isset($request['requested_package_id']) ? absint($request['requested_package_id']) : 0;
        $requested_package = $requested_package_id ? $this->repo->get_package($requested_package_id) : null;
        if ($requested_package_id && !$requested_package) {
            $requested_package_id = 0;
        }

        $canonical_package_state = $this->package_form_state($requested_package);
        $submitted_package_snapshot = $this->sanitize_submitted_package_snapshot();
        $submitted_consideration_type = sanitize_key($request['consideration_type'] ?? $canonical_package_state['consideration_type']);
        if (!array_key_exists($submitted_consideration_type, $this->consideration_type_choices())) {
            $submitted_consideration_type = $canonical_package_state['consideration_type'];
        }

        $trade_offer_details = $request['trade_offer_details'] ?? '';
        $estimated_trade_value = $request['estimated_trade_value'] ?? '';
        if (!$this->consideration_type_uses_trade_fields($submitted_consideration_type)) {
            $trade_offer_details = '';
            $estimated_trade_value = '';
        }

        $canonical_submission_snapshot = array(
            'selectedPackageName' => $canonical_package_state['name'],
            'selectedPackageValue' => $canonical_package_state['value'],
            'selectedPackageType' => $canonical_package_state['type'],
            'sponsorshipPackageInterest' => $canonical_package_state['interest_label'],
            'sponsorshipConsiderationType' => $this->consideration_type_label($submitted_consideration_type),
        );

        $application_payload = array(
            'event_id' => $request['event_id'] ?? 0,
            'season_id' => $request['season_id'] ?? 0,
            'requested_package_id' => $requested_package_id,
            'requested_slot_key' => $request['requested_slot_key'] ?? '',
            'consideration_type' => $submitted_consideration_type,
            'trade_offer_details' => $trade_offer_details,
            'estimated_trade_value' => $estimated_trade_value,
            'scope' => $request['scope'] ?? 'event',
            'business_name' => $request['business_name'] ?? '',
            'contact_name' => $request['contact_name'] ?? '',
            'email' => $request['email'] ?? '',
            'phone' => $request['phone'] ?? '',
            'website_url' => $request['website_url'] ?? '',
            'business_category' => $request['business_category'] ?? '',
            'sponsor_message' => $request['sponsor_message'] ?? '',
        );

        $application_id = $this->repo->create_application(array_merge($application_payload, $submitted_package_snapshot, $canonical_submission_snapshot));

        if (is_wp_error($application_id)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'application'));
        }

        $this->mark_recent_application_submission($request);
        $this->notifications->new_application($application_id);
        $this->safe_front_redirect(array('vms_sponsor_submitted' => 1));
    }

    private function handle_asset_upload() {
        $request = wp_unslash($_POST);

        if (!is_user_logged_in()) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'login_required'));
        }

        $nonce = sanitize_text_field($request['_wpnonce'] ?? '');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_sponsorships_upload_asset')) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'nonce'));
        }

        if ($this->is_honeypot_tripped($request)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'spam'));
        }

        if (empty($_FILES['sponsor_asset']['name'])) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'missing_asset'));
        }

        $assignment_id = isset($request['assignment_id']) ? absint($request['assignment_id']) : 0;
        $application_id = isset($request['application_id']) ? absint($request['application_id']) : 0;
        $context = $this->resolve_asset_upload_context($assignment_id, $application_id);

        if (is_wp_error($context)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => sanitize_key($context->get_error_code())));
        }

        if (!$this->current_user_can_upload_for_context($context['assignment'], $context['application'])) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'permission'));
        }

        if ($this->has_recent_asset_upload($_FILES['sponsor_asset'], $context)) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'duplicate_submission'));
        }

        $allowed_mimes = apply_filters('vms_sponsorships_allowed_asset_mimes', array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ));

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = array(
            'test_form' => false,
            'mimes' => $allowed_mimes,
        );

        $uploaded = wp_handle_upload($_FILES['sponsor_asset'], $overrides);
        if (!empty($uploaded['error'])) {
            $this->safe_front_redirect(array('vms_sponsor_error' => 'upload'));
        }

        $original_filename = sanitize_file_name(wp_basename((string) ($_FILES['sponsor_asset']['name'] ?? '')));
        $validated_type = wp_check_filetype_and_ext($uploaded['file'], $original_filename);
        $allowed_mime_values = array_values($allowed_mimes);
        if (empty($validated_type['ext']) || empty($validated_type['type']) || !in_array($validated_type['type'], $allowed_mime_values, true)) {
            $this->delete_uploaded_file($uploaded['file']);
            $this->safe_front_redirect(array('vms_sponsor_error' => 'invalid_file_type'));
        }

        $attachment = array(
            'post_author' => get_current_user_id(),
            'post_mime_type' => $validated_type['type'],
            'post_title' => sanitize_file_name(basename($uploaded['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (is_wp_error($attachment_id) || !$attachment_id) {
            $this->delete_uploaded_file($uploaded['file']);
            $this->safe_front_redirect(array('vms_sponsor_error' => 'upload'));
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        $asset_id = $this->repo->add_asset(array(
            'assignment_id' => $context['assignment_id'],
            'application_id' => $context['application_id'],
            'asset_type' => $request['asset_type'] ?? 'logo',
            'attachment_id' => $attachment_id,
            'status' => 'pending_review',
            'user_id' => get_current_user_id(),
        ));

        if (is_wp_error($asset_id)) {
            wp_delete_attachment($attachment_id, true);
            $this->safe_front_redirect(array('vms_sponsor_error' => 'upload'));
        }

        $this->mark_recent_asset_upload($_FILES['sponsor_asset'], $context);
        $this->notifications->asset_uploaded($asset_id, (int) $context['assignment_id']);
        $this->safe_front_redirect(array('vms_sponsor_asset_uploaded' => 1));
    }

    public function maybe_render_built_in_application_page() {
        if ($this->get_query_var('vms_sponsor_apply') === '' || is_admin() || wp_doing_ajax()) {
            return;
        }

        $event_id = $this->get_query_absint('vms_sponsor_event_id');
        $season_id = $this->get_query_absint('vms_sponsor_season_id');
        $scope = $this->get_query_key('vms_sponsor_scope', 'event');
        $package_id = $this->get_query_absint('package_id');

        status_header(200);
        nocache_headers();

        get_header();
        echo '<main id="primary" class="site-main vms-sponsor-apply-page">';
        echo '<div class="vms-sponsor-apply-page__inner">';
        echo '<h1>' . esc_html__('Sponsorship Application', 'vms-sponsorships') . '</h1>';
        echo '<p>' . esc_html__('Tell us a little about your business and the sponsorship opportunity you are interested in. Submissions require operator approval before payment or public display.', 'vms-sponsorships') . '</p>';
        echo $this->application_form_shortcode(array(
            'event_id' => $event_id,
            'season_id' => $season_id,
            'scope' => $scope,
            'package_id' => $package_id,
        ));
        echo '</div>';
        echo '</main>';
        get_footer();
        exit;
    }

    public function application_form_shortcode($atts) {
        $query_event_id = $this->get_query_absint('vms_sponsor_event_id', $this->get_query_absint('event_id', get_the_ID()));
        $query_season_id = $this->get_query_absint('vms_sponsor_season_id', $this->get_query_absint('season_id'));
        $query_scope = $this->get_query_key('vms_sponsor_scope', $this->get_query_key('scope', 'event'));
        $query_slot = $this->get_query_key('vms_sponsor_slot', 'presenting');
        $query_package_id = $this->get_query_absint('vms_sponsor_package_id', $this->get_query_absint('package_id'));

        $atts = shortcode_atts(array(
            'event_id' => $query_event_id,
            'season_id' => $query_season_id,
            'scope' => $query_scope,
            'slot' => $query_slot,
            'package_id' => $query_package_id,
        ), $atts, 'vms_sponsor_apply');

        wp_enqueue_style('vms-sponsorships-public');
        $packages = $this->public_inquiry_packages(absint($atts['package_id']));
        $selected_package = absint($atts['package_id']) ? $this->repo->get_package(absint($atts['package_id'])) : null;
        $selected_package_state = $this->package_form_state($selected_package);
        $context_title = $this->context_title(absint($atts['event_id']), absint($atts['season_id']), sanitize_key($atts['scope']));
        $context_date = $this->context_date(absint($atts['event_id']));
        $consideration_types = $this->consideration_type_choices();
        $initial_consideration_type = $selected_package_state['consideration_type'];
        $initial_trade_value = $selected_package_state['estimated_trade_value'];
        $show_trade_fields = $this->consideration_type_uses_trade_fields($initial_consideration_type);
        $return_url = $this->current_request_url();

        ob_start();
        $this->render_public_messages();
        ?>
        <form class="vms-sponsor-application-form" id="vms-sponsor-application-form" method="post">
            <?php wp_nonce_field('vms_sponsorships_submit_application'); ?>
            <input type="hidden" name="vms_sponsorships_public_action" value="submit_application">
            <input type="hidden" name="event_id" value="<?php echo esc_attr(absint($atts['event_id'])); ?>">
            <input type="hidden" name="season_id" value="<?php echo esc_attr(absint($atts['season_id'])); ?>">
            <input type="hidden" name="scope" value="<?php echo esc_attr(sanitize_key($atts['scope'])); ?>">
            <input type="hidden" name="requested_slot_key" value="<?php echo esc_attr(sanitize_key($atts['slot'])); ?>">
            <input type="hidden" name="vms_sponsor_return_url" value="<?php echo esc_attr($return_url); ?>">
            <input type="hidden" name="selectedPackageName" value="<?php echo esc_attr($selected_package_state['name']); ?>">
            <input type="hidden" name="selectedPackageValue" value="<?php echo esc_attr($selected_package_state['value']); ?>">
            <input type="hidden" name="selectedPackageType" value="<?php echo esc_attr($selected_package_state['type']); ?>">
            <input type="hidden" name="sponsorshipPackageInterest" value="<?php echo esc_attr($selected_package_state['interest_label']); ?>">
            <input type="hidden" name="sponsorshipConsiderationType" value="<?php echo esc_attr($selected_package_state['consideration_label']); ?>">
            <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label><?php esc_html_e('Website', 'vms-sponsorships'); ?>
                    <input type="text" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" value="" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <?php if ($context_title || $selected_package || !empty($atts['slot'])) : ?>
                <div class="vms-sponsor-form__context">
                    <?php if ($context_title) : ?>
                        <strong class="vms-sponsor-form__context-title"><?php echo esc_html($context_title); ?></strong>
                    <?php endif; ?>
                    <div class="vms-sponsor-form__context-meta">
                        <?php if ($context_date) : ?>
                            <span><?php echo esc_html($context_date); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($atts['slot'])) : ?>
                            <span><?php echo esc_html($this->slot_label($atts['slot'])); ?></span>
                        <?php endif; ?>
                        <?php if ($selected_package) : ?>
                            <span data-selected-package-name><?php echo esc_html($selected_package->name); ?></span>
                            <?php if ($selected_package_state['value_display']) : ?>
                                <span data-selected-package-value><?php echo esc_html($selected_package_state['value_display']); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <p><?php esc_html_e('Tell us about your business and goals. No payment is collected when you apply, and we review each sponsorship first to make sure the placement is available and the fit is right.', 'vms-sponsorships'); ?></p>
                </div>
            <?php endif; ?>

            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Business or sponsor name', 'vms-sponsorships'); ?><br>
                    <input type="text" name="business_name" required>
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Contact name', 'vms-sponsorships'); ?><br>
                    <input type="text" name="contact_name">
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Email', 'vms-sponsorships'); ?><br>
                    <input type="email" name="email" required>
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Phone', 'vms-sponsorships'); ?><br>
                    <input type="text" name="phone">
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Website', 'vms-sponsorships'); ?><br>
                    <input type="url" name="website_url">
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Business category', 'vms-sponsorships'); ?><br>
                    <input type="text" name="business_category" placeholder="<?php esc_attr_e('Restaurant, roofing, nonprofit, etc.', 'vms-sponsorships'); ?>">
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Sponsorship package interest', 'vms-sponsorships'); ?><br>
                    <select name="requested_package_id">
                        <option
                            value="0"
                            data-package-name="<?php echo esc_attr($this->not_sure_label()); ?>"
                            data-package-value=""
                            data-package-value-display=""
                            data-package-type=""
                            data-package-consideration="not_sure"
                            data-package-consideration-label="<?php echo esc_attr($this->consideration_type_label('not_sure')); ?>"
                            <?php selected(absint($atts['package_id']), 0); ?>
                        ><?php esc_html_e('Not sure yet', 'vms-sponsorships'); ?></option>
                        <?php foreach ($packages as $package) : ?>
                            <?php $package_state = $this->package_form_state($package); ?>
                            <option
                                value="<?php echo esc_attr($package->id); ?>"
                                data-package-name="<?php echo esc_attr($package_state['name']); ?>"
                                data-package-value="<?php echo esc_attr($package_state['value']); ?>"
                                data-package-value-display="<?php echo esc_attr($package_state['value_display']); ?>"
                                data-package-type="<?php echo esc_attr($package_state['type']); ?>"
                                data-package-consideration="<?php echo esc_attr($package_state['consideration_type']); ?>"
                                data-package-consideration-label="<?php echo esc_attr($package_state['consideration_label']); ?>"
                                <?php selected(absint($atts['package_id']), $package->id); ?>
                            ><?php echo esc_html($package->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Sponsorship consideration type', 'vms-sponsorships'); ?><br>
                    <select name="consideration_type">
                        <?php foreach ($consideration_types as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($initial_consideration_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="vms-sponsor-form__row" data-vms-sponsor-trade-details-row<?php echo $show_trade_fields ? '' : ' hidden'; ?>>
                <label><?php esc_html_e('Trade / in-kind offer details', 'vms-sponsorships'); ?><br>
                    <textarea name="trade_offer_details" rows="4"<?php echo $show_trade_fields ? '' : ' disabled'; ?>></textarea>
                </label>
                <p class="vms-sponsor-field__hint"><?php esc_html_e('If you are offering services, goods, or trade value, tell us what you can provide, how often, and any estimated value.', 'vms-sponsorships'); ?></p>
            </div>
            <div class="vms-sponsor-form__row" data-vms-sponsor-trade-value-row<?php echo $show_trade_fields ? '' : ' hidden'; ?>>
                <label><?php esc_html_e('Estimated trade value', 'vms-sponsorships'); ?><br>
                    <input type="number" step="0.01" min="0" name="estimated_trade_value" value="<?php echo esc_attr($initial_trade_value); ?>" placeholder="<?php esc_attr_e('Optional', 'vms-sponsorships'); ?>"<?php echo $show_trade_fields ? '' : ' disabled'; ?>>
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><?php esc_html_e('Message', 'vms-sponsorships'); ?><br>
                    <textarea name="sponsor_message" rows="5"></textarea>
                </label>
            </div>
            <div class="vms-sponsor-form__row">
                <label><input type="checkbox" required> <?php esc_html_e('I understand sponsorship submissions require operator approval before payment or public display.', 'vms-sponsorships'); ?></label>
            </div>
            <button class="vms-sponsor-form__submit" type="submit"><?php esc_html_e('Submit sponsorship application', 'vms-sponsorships'); ?></button>
        </form>
        <script>
        (function () {
            var script = document.currentScript;
            var form = script ? script.previousElementSibling : null;
            if (!form || !form.classList || !form.classList.contains('vms-sponsor-application-form')) {
                return;
            }

            var packageSelect = form.querySelector('select[name="requested_package_id"]');
            var considerationSelect = form.querySelector('select[name="consideration_type"]');
            var tradeDetailsRow = form.querySelector('[data-vms-sponsor-trade-details-row]');
            var tradeDetailsInput = form.querySelector('textarea[name="trade_offer_details"]');
            var tradeValueRow = form.querySelector('[data-vms-sponsor-trade-value-row]');
            var tradeValueInput = form.querySelector('input[name="estimated_trade_value"]');

            if (!packageSelect || !considerationSelect) {
                return;
            }

            var hiddenName = form.querySelector('input[name="selectedPackageName"]');
            var hiddenValue = form.querySelector('input[name="selectedPackageValue"]');
            var hiddenType = form.querySelector('input[name="selectedPackageType"]');
            var hiddenInterest = form.querySelector('input[name="sponsorshipPackageInterest"]');
            var hiddenConsideration = form.querySelector('input[name="sponsorshipConsiderationType"]');
            var packageContextName = form.querySelector('[data-selected-package-name]');
            var packageContextValue = form.querySelector('[data-selected-package-value]');

            var considerationDirty = false;
            var tradeValueDirty = false;
            var lastAutoConsideration = considerationSelect.value;

            function selectedPackageOption() {
                return packageSelect.options[packageSelect.selectedIndex] || null;
            }

            function selectedConsiderationLabel() {
                var option = considerationSelect.options[considerationSelect.selectedIndex];
                return option ? option.text : '';
            }

            function syncHiddenConsiderationLabel() {
                if (hiddenConsideration) {
                    hiddenConsideration.value = selectedConsiderationLabel();
                }
            }

            function considerationUsesTradeFields(value) {
                return value === 'trade_in_kind' || value === 'cash_and_trade';
            }

            function syncTradeFieldVisibility() {
                var showTradeFields = considerationUsesTradeFields(considerationSelect.value);

                if (tradeDetailsRow) {
                    tradeDetailsRow.hidden = !showTradeFields;
                }
                if (tradeValueRow) {
                    tradeValueRow.hidden = !showTradeFields;
                }
                if (tradeDetailsInput) {
                    tradeDetailsInput.disabled = !showTradeFields;
                }
                if (tradeValueInput) {
                    tradeValueInput.disabled = !showTradeFields;
                }
            }

            function applyPackageSelection() {
                var option = selectedPackageOption();
                if (!option) {
                    return;
                }

                var packageName = option.dataset.packageName || '';
                var packageValue = option.dataset.packageValue || '';
                var packageValueDisplay = option.dataset.packageValueDisplay || '';
                var packageType = option.dataset.packageType || '';
                var packageConsideration = option.dataset.packageConsideration || 'not_sure';
                var packageUsesTradeFields = considerationUsesTradeFields(packageConsideration);
                var hasRealPackage = packageSelect.value !== '0' && packageName !== '';

                if (hiddenName) {
                    hiddenName.value = packageName;
                }
                if (hiddenValue) {
                    hiddenValue.value = packageValue;
                }
                if (hiddenType) {
                    hiddenType.value = packageType;
                }
                if (hiddenInterest) {
                    hiddenInterest.value = packageName;
                }
                if (packageContextName) {
                    packageContextName.textContent = packageName;
                    packageContextName.hidden = !hasRealPackage;
                }
                if (packageContextValue) {
                    packageContextValue.textContent = packageValueDisplay;
                    packageContextValue.hidden = !hasRealPackage || !packageValueDisplay;
                }

                if (packageUsesTradeFields) {
                    considerationSelect.value = packageConsideration;
                    lastAutoConsideration = packageConsideration;
                } else if (!considerationDirty || considerationSelect.value === 'not_sure' || considerationSelect.value === lastAutoConsideration) {
                    considerationSelect.value = packageConsideration;
                    lastAutoConsideration = packageConsideration;
                }

                if (tradeValueInput && !tradeValueDirty) {
                    if (packageUsesTradeFields && packageValue !== '') {
                        tradeValueInput.value = packageValue;
                    } else {
                        tradeValueInput.value = '';
                    }
                }

                syncHiddenConsiderationLabel();
                syncTradeFieldVisibility();
            }

            considerationSelect.addEventListener('change', function () {
                considerationDirty = true;
                syncHiddenConsiderationLabel();
                syncTradeFieldVisibility();
            });

            if (tradeValueInput) {
                tradeValueInput.addEventListener('input', function () {
                    tradeValueDirty = true;
                });
            }

            packageSelect.addEventListener('change', applyPackageSelection);
            applyPackageSelection();
        }());
        </script>
        <?php
        return trim(ob_get_clean());
    }

    public function inquiry_page_shortcode($atts) {
        $context = $this->resolve_context();
        $atts = shortcode_atts(array(
            'event_id' => $context['event_id'],
            'season_id' => $context['season_id'],
            'scope' => $context['scope'],
            'slot' => $context['slot'],
            'package_id' => $context['package_id'],
            'show_form' => 'yes',
            'headline' => '',
            'intro' => '',
            'cta_label' => __('View sponsorship packages', 'vms-sponsorships'),
        ), $atts, 'vms_sponsor_inquiry');

        $event_id = absint($atts['event_id']);
        $season_id = absint($atts['season_id']);
        $scope = sanitize_key($atts['scope']);
        $slot = sanitize_key($atts['slot']);
        $package_id = absint($atts['package_id']);
        $show_form = 'no' !== strtolower((string) $atts['show_form']);

        wp_enqueue_style('vms-sponsorships-public');

        $packages = $this->public_inquiry_packages($package_id);
        $visibility_section = $this->visibility_section_data();
        $context_title = $this->context_title($event_id, $season_id, $scope);
        $context_date = $this->context_date($event_id);
        $headline = $atts['headline'] ? $atts['headline'] : __('Sponsor This Show at Serenade Range', 'vms-sponsorships');
        $intro = $atts['intro'] ? $atts['intro'] : __('Put your business in front of East Texas music fans before, during, and after the event.', 'vms-sponsorships');
        $context_line = $this->context_sentence($context_title, $context_date, $scope);
        $packages_section_id = 'vms-sponsor-package-options';
        $form_anchor = '#vms-sponsor-application-form';
        $hero_cta_url = !empty($packages)
            ? '#' . $packages_section_id
            : ($show_form ? $this->inquiry_package_url($package_id, $form_anchor) : '');
        $continue_without_package_url = $show_form ? $this->inquiry_package_url(0, $form_anchor) : $this->inquiry_package_url(0);

        ob_start();
        ?>
        <div class="vms-sponsor-inquiry">
            <section class="vms-sponsor-inquiry__hero">
                <div class="vms-sponsor-inquiry__eyebrow"><?php echo esc_html($this->slot_label($slot)); ?></div>
                <h1 class="vms-sponsor-inquiry__title"><?php echo esc_html($headline); ?></h1>
                <p class="vms-sponsor-inquiry__intro"><?php echo esc_html($intro); ?></p>
                <?php if ($context_line) : ?>
                    <p class="vms-sponsor-inquiry__context-line"><?php echo esc_html($context_line); ?></p>
                <?php endif; ?>
                <div class="vms-sponsor-inquiry__meta">
                    <?php if ($context_title) : ?>
                        <span><?php echo esc_html($context_title); ?></span>
                    <?php endif; ?>
                    <?php if ($context_date) : ?>
                        <span><?php echo esc_html($context_date); ?></span>
                        <?php endif; ?>
                        <span><?php echo esc_html($this->scope_label($scope)); ?></span>
                </div>
                <p class="vms-sponsor-inquiry__note"><?php esc_html_e('No payment is collected when you apply. We review each sponsorship first to make sure it is a good fit and that the placement is available.', 'vms-sponsorships'); ?></p>
                <p class="vms-sponsor-inquiry__trade-note"><?php esc_html_e('We also consider select trade or in-kind sponsorships, including services that help support the venue. All trade sponsorships are reviewed and approved before any public recognition goes live.', 'vms-sponsorships'); ?></p>
                <?php if ($hero_cta_url) : ?>
                    <a class="vms-sponsor-inquiry__primary" href="<?php echo esc_url($hero_cta_url); ?>"><?php echo esc_html($atts['cta_label']); ?></a>
                <?php endif; ?>
            </section>

            <?php if (!empty($packages)) : ?>
                <section class="vms-sponsor-inquiry__packages" id="<?php echo esc_attr($packages_section_id); ?>">
                    <div class="vms-sponsor-inquiry__section-head">
                        <h2><?php esc_html_e('Sponsorship options', 'vms-sponsorships'); ?></h2>
                        <p><?php esc_html_e('Choose the level that fits your business best. If you are unsure, start the application and we can help match you to the right option.', 'vms-sponsorships'); ?></p>
                    </div>
                    <div class="vms-sponsor-package-grid">
                        <?php foreach ($packages as $package) : ?>
                            <?php $is_selected = $package_id && $package_id === (int) $package->id; ?>
                            <article class="<?php echo esc_attr($this->package_card_class_names($package, $is_selected)); ?>">
                                <div class="vms-sponsor-package-card__header">
                                    <strong class="vms-sponsor-package-card__name"><?php echo esc_html($package->name); ?></strong>
                                    <span class="vms-sponsor-package-card__price"><?php echo esc_html($this->format_price($package->base_price)); ?></span>
                                </div>
                                <?php $package_description = $this->package_sales_description($package); ?>
                                <?php if ($package_description) : ?>
                                    <div class="vms-sponsor-package-card__body"><?php echo wp_kses_post(wpautop($package_description)); ?></div>
                                <?php endif; ?>
                                <?php $benefits = $this->package_benefits($package); ?>
                                <?php if (!empty($benefits)) : ?>
                                    <ul class="vms-sponsor-package-card__facts">
                                        <?php foreach ($benefits as $benefit) : ?>
                                            <li><?php echo esc_html($benefit); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <a class="vms-sponsor-package-card__cta" data-vms-sponsor-nav href="<?php echo esc_url($this->inquiry_package_url((int) $package->id, $show_form ? $form_anchor : '')); ?>">
                                    <?php esc_html_e('Choose this package', 'vms-sponsorships'); ?>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($show_form) : ?>
                        <div class="vms-sponsor-inquiry__secondary-cta">
                            <p><?php esc_html_e('Need help deciding first? You can still continue without selecting a package.', 'vms-sponsorships'); ?></p>
                            <a class="vms-sponsor-inquiry__secondary" data-vms-sponsor-nav href="<?php echo esc_url($continue_without_package_url); ?>"><?php esc_html_e('Not sure yet — continue to application', 'vms-sponsorships'); ?></a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($this->should_render_visibility_section($visibility_section)) : ?>
                <section class="vms-sponsor-inquiry__visibility">
                    <div class="vms-sponsor-inquiry__section-head">
                        <?php if ($visibility_section['title']) : ?>
                            <h2><?php echo esc_html($visibility_section['title']); ?></h2>
                        <?php endif; ?>
                        <?php if ($visibility_section['intro']) : ?>
                            <p><?php echo esc_html($visibility_section['intro']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($visibility_section['stats'])) : ?>
                        <div class="vms-sponsor-visibility-grid">
                            <?php foreach ($visibility_section['stats'] as $stat) : ?>
                                <article class="vms-sponsor-visibility-card">
                                    <span class="vms-sponsor-visibility-card__label"><?php echo esc_html($stat['label']); ?></span>
                                    <strong class="vms-sponsor-visibility-card__value"><?php echo esc_html($stat['value']); ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($visibility_section['last_updated']) : ?>
                        <p class="vms-sponsor-visibility__updated"><?php echo esc_html($visibility_section['last_updated']); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($show_form) : ?>
                <section class="vms-sponsor-inquiry__form-wrap">
                    <div class="vms-sponsor-inquiry__section-head">
                        <h2><?php esc_html_e('Start your application', 'vms-sponsorships'); ?></h2>
                        <p><?php esc_html_e('If you are not sure which package fits best, submit the form anyway and we can sort out the details during review.', 'vms-sponsorships'); ?></p>
                    </div>
                    <?php
                    echo $this->application_form_shortcode(array(
                        'event_id' => $event_id,
                        'season_id' => $season_id,
                        'scope' => $scope,
                        'slot' => $slot,
                        'package_id' => $package_id,
                    ));
                    ?>
                </section>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            var root = document.currentScript ? document.currentScript.previousElementSibling : null;
            if (!root || !root.classList || !root.classList.contains('vms-sponsor-inquiry')) {
                return;
            }

            root.querySelectorAll('[data-vms-sponsor-nav]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var href = link.getAttribute('href');
                    if (!href) {
                        return;
                    }

                    event.preventDefault();
                    window.location.assign(href);
                });
            });
        }());
        </script>
        <?php
        return trim(ob_get_clean());
    }

    public function asset_upload_shortcode($atts) {
        $atts = shortcode_atts(array(
            'assignment_id' => 0,
            'application_id' => 0,
            'asset_type' => 'logo',
        ), $atts, 'vms_sponsor_asset_upload');

        wp_enqueue_style('vms-sponsorships-public');
        $assignment_id = absint($atts['assignment_id']);
        $application_id = absint($atts['application_id']);
        $context = $this->resolve_asset_upload_context($assignment_id, $application_id);
        $return_url = $this->current_request_url();

        ob_start();
        $this->render_public_messages();

        if (!is_user_logged_in()) {
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--error">' . esc_html__('Please log in to upload sponsor assets.', 'vms-sponsorships') . '</p>';
            return trim(ob_get_clean());
        }

        if (is_wp_error($context)) {
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--error">' . esc_html__('This upload form is missing a valid sponsorship context.', 'vms-sponsorships') . '</p>';
            return trim(ob_get_clean());
        }

        if (!$this->current_user_can_upload_for_context($context['assignment'], $context['application'])) {
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--error">' . esc_html__('You do not have permission to upload assets for this sponsorship.', 'vms-sponsorships') . '</p>';
            return trim(ob_get_clean());
        }
        ?>
        <form class="vms-sponsor-asset-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('vms_sponsorships_upload_asset'); ?>
            <input type="hidden" name="vms_sponsorships_public_action" value="upload_asset">
            <input type="hidden" name="assignment_id" value="<?php echo esc_attr($context['assignment_id']); ?>">
            <input type="hidden" name="application_id" value="<?php echo esc_attr($context['application_id']); ?>">
            <input type="hidden" name="asset_type" value="<?php echo esc_attr(sanitize_key($atts['asset_type'])); ?>">
            <input type="hidden" name="vms_sponsor_return_url" value="<?php echo esc_attr($return_url); ?>">
            <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label><?php esc_html_e('Website', 'vms-sponsorships'); ?>
                    <input type="text" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" value="" tabindex="-1" autocomplete="off">
                </label>
            </div>
            <label><?php esc_html_e('Upload sponsor asset', 'vms-sponsorships'); ?><br>
                <input type="file" name="sponsor_asset" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
            </label>
            <p class="description"><?php esc_html_e('Uploaded graphics enter pending review and will not appear publicly until approved.', 'vms-sponsorships'); ?></p>
            <p class="description"><?php esc_html_e('Accepted file types: JPG, PNG, GIF, WebP, and PDF. SVG uploads are blocked unless a site-specific sanitization layer is added separately.', 'vms-sponsorships'); ?></p>
            <button type="submit"><?php esc_html_e('Upload for review', 'vms-sponsorships'); ?></button>
        </form>
        <?php
        return trim(ob_get_clean());
    }

    private function render_public_messages() {
        if ($this->get_query_var('vms_sponsor_submitted') !== '') {
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--success">' . esc_html__('Thank you. Your sponsorship application has been submitted for review.', 'vms-sponsorships') . '</p>';
        }
        if ($this->get_query_var('vms_sponsor_asset_uploaded') !== '') {
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--success">' . esc_html__('Your asset has been uploaded and is pending review.', 'vms-sponsorships') . '</p>';
        }
        if ($this->get_query_var('vms_sponsor_error') !== '') {
            $error = $this->get_query_key('vms_sponsor_error');
            $labels = array(
                'nonce' => __('Security check failed. Please try again.', 'vms-sponsorships'),
                'application' => __('The application could not be submitted. Please check the required fields.', 'vms-sponsorships'),
                'login_required' => __('Please log in before uploading sponsor assets.', 'vms-sponsorships'),
                'missing_asset' => __('Please choose a file to upload.', 'vms-sponsorships'),
                'upload' => __('The asset could not be uploaded.', 'vms-sponsorships'),
                'missing_context' => __('This upload form is missing a valid sponsorship context.', 'vms-sponsorships'),
                'permission' => __('You do not have permission to submit assets for this sponsorship.', 'vms-sponsorships'),
                'spam' => __('Your submission looked automated and was not accepted.', 'vms-sponsorships'),
                'duplicate_submission' => __('This submission was already received recently. Please wait a moment before trying again.', 'vms-sponsorships'),
                'invalid_file_type' => __('That file type is not allowed. Please upload a safe image or PDF.', 'vms-sponsorships'),
            );
            echo '<p class="vms-sponsor-notice vms-sponsor-notice--error">' . esc_html($labels[$error] ?? __('Something went wrong.', 'vms-sponsorships')) . '</p>';
        }
    }

    private function safe_front_redirect($args) {
        $url = '';
        if (!empty($_POST['vms_sponsor_return_url'])) {
            $url = esc_url_raw(wp_unslash($_POST['vms_sponsor_return_url']));
        }

        if (!$url) {
            $url = wp_get_referer();
        }
        if (!$url) {
            $url = home_url('/');
        }

        $clean_url = remove_query_arg(array('vms_sponsor_submitted', 'vms_sponsor_error', 'vms_sponsor_asset_uploaded'), $url);
        wp_safe_redirect(add_query_arg($args, $clean_url));
        exit;
    }

    private function resolve_context() {
        return array(
            'event_id' => $this->get_query_absint('vms_sponsor_event_id', $this->get_query_absint('event_id')),
            'season_id' => $this->get_query_absint('vms_sponsor_season_id', $this->get_query_absint('season_id')),
            'scope' => $this->get_query_key('vms_sponsor_scope', $this->get_query_key('scope', 'event')),
            'slot' => $this->get_query_key('vms_sponsor_slot', 'presenting'),
            'package_id' => $this->get_query_absint('vms_sponsor_package_id', $this->get_query_absint('package_id')),
        );
    }

    private function public_inquiry_packages($selected_package_id = 0) {
        $packages = $this->repo->get_packages(true);
        $selected_package_id = absint($selected_package_id);

        if ($selected_package_id) {
            $selected_package = $this->repo->get_package($selected_package_id);
            if ($selected_package && (int) $selected_package->active && !$this->package_list_contains($packages, $selected_package_id)) {
                $packages[] = $selected_package;
            }
        }

        usort($packages, function ($left, $right) {
            $left_priority = $this->package_display_priority($left);
            $right_priority = $this->package_display_priority($right);

            if ($left_priority !== $right_priority) {
                return $left_priority <=> $right_priority;
            }

            $left_price = isset($left->base_price) ? (float) $left->base_price : 0;
            $right_price = isset($right->base_price) ? (float) $right->base_price : 0;
            if ($left_price !== $right_price) {
                return $right_price <=> $left_price;
            }

            return strnatcasecmp((string) ($left->name ?? ''), (string) ($right->name ?? ''));
        });

        return apply_filters('vms_sponsorships_public_inquiry_packages', $packages, $selected_package_id);
    }

    private function package_list_contains($packages, $package_id) {
        foreach ($packages as $package) {
            if ((int) ($package->id ?? 0) === (int) $package_id) {
                return true;
            }
        }

        return false;
    }

    private function package_display_priority($package) {
        $profile = $this->default_package_profile($package);

        return $profile ? (int) ($profile['display_priority'] ?? 1000) : 1000;
    }

    private function visibility_section_data() {
        $visibility = VMS_Sponsorships_Visibility_Stats::load();
        $last_updated = implode(' ', array_filter(array(
            $visibility['public_last_updated_text'],
            __('Manually maintained sponsor visibility proof points.', 'vms-sponsorships'),
        )));

        return array(
            'title' => $visibility['title'],
            'intro' => $visibility['intro'],
            'stats' => $visibility['stats'],
            'last_updated' => $last_updated,
        );
    }

    private function should_render_visibility_section($section) {
        return !empty($section['stats']);
    }

    private function inquiry_package_url($package_id = 0, $anchor = '') {
        $context = $this->resolve_context();
        $url = get_permalink();
        if (!$url) {
            $url = home_url('/');
        }

        $args = array(
            'vms_sponsor_slot' => $context['slot'],
            'vms_sponsor_scope' => $context['scope'],
        );

        if (!empty($context['event_id'])) {
            $args['vms_sponsor_event_id'] = absint($context['event_id']);
        }

        if (!empty($context['season_id'])) {
            $args['vms_sponsor_season_id'] = absint($context['season_id']);
        }

        if ($package_id > 0) {
            $args['vms_sponsor_package_id'] = absint($package_id);
        }

        $url = add_query_arg($args, $url);

        return $anchor ? $url . $anchor : $url;
    }

    private function context_title($event_id, $season_id, $scope) {
        if ($event_id) {
            return get_the_title($event_id);
        }

        if ($season_id) {
            return sprintf(__('Season %d', 'vms-sponsorships'), $season_id);
        }

        return $this->scope_label($scope);
    }

    private function context_date($event_id) {
        if (!$event_id) {
            return '';
        }

        if (function_exists('tribe_get_start_date')) {
            return tribe_get_start_date($event_id, false, get_option('date_format'));
        }

        $timestamp = get_post_time('U', false, $event_id);
        if (!$timestamp) {
            return '';
        }

        return wp_date(get_option('date_format'), $timestamp);
    }

    private function context_sentence($context_title, $context_date, $scope) {
        if ($context_title && $context_date) {
            return sprintf(
                __('This opportunity is connected to %1$s on %2$s.', 'vms-sponsorships'),
                $context_title,
                $context_date
            );
        }

        if ($context_title) {
            return sprintf(
                __('This opportunity is connected to %s.', 'vms-sponsorships'),
                $context_title
            );
        }

        if ($scope) {
            return sprintf(
                __('This opportunity is connected to a %s placement.', 'vms-sponsorships'),
                strtolower($this->scope_label($scope))
            );
        }

        return '';
    }

    private function format_price($amount) {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return __('Custom quote', 'vms-sponsorships');
        }

        if ((int) $amount === $amount) {
            return '$' . number_format_i18n($amount, 0);
        }

        return '$' . number_format_i18n($amount, 2);
    }

    private function package_form_state($package) {
        if (!$package) {
            return array(
                'name' => $this->not_sure_label(),
                'value' => '',
                'value_display' => '',
                'type' => '',
                'interest_label' => $this->not_sure_label(),
                'consideration_type' => 'not_sure',
                'consideration_label' => $this->consideration_type_label('not_sure'),
                'estimated_trade_value' => '',
            );
        }

        $consideration_type = $this->default_consideration_type_for_package($package);
        $base_price = isset($package->base_price) ? (float) $package->base_price : 0;
        $base_price_value = $base_price > 0 ? $this->normalize_money_for_input($base_price) : '';

        return array(
            'name' => sanitize_text_field($package->name ?? ''),
            'value' => $base_price_value,
            'value_display' => $base_price > 0 ? $this->format_price($base_price) : '',
            'type' => sanitize_key($package->scope ?? ''),
            'interest_label' => sanitize_text_field($package->name ?? ''),
            'consideration_type' => $consideration_type,
            'consideration_label' => $this->consideration_type_label($consideration_type),
            'estimated_trade_value' => $this->consideration_type_uses_trade_fields($consideration_type) ? $base_price_value : '',
        );
    }

    private function package_card_class_names($package, $is_selected = false) {
        $classes = array('vms-sponsor-package-card');
        $slug = sanitize_title($package->slug ?? '');

        if ($is_selected) {
            $classes[] = 'is-selected';
        }

        if ($slug) {
            $classes[] = 'vms-sponsor-package-card--' . $slug;
        }

        if (in_array($slug, array('presenting-sponsor', 'supporting-sponsor'), true)) {
            $classes[] = 'vms-sponsor-package-card--lead';
        } else {
            $classes[] = 'vms-sponsor-package-card--support';
        }

        return implode(' ', array_map('sanitize_html_class', array_unique($classes)));
    }

    private function package_sales_description($package) {
        $description = trim(wp_strip_all_tags((string) ($package->description ?? '')));
        $profile = $this->default_package_profile($package);

        if ($profile && ('' === $description || in_array($description, $profile['legacy_descriptions'], true))) {
            return $profile['description'];
        }

        return $package->description ?? '';
    }

    private function package_benefits($package) {
        $benefits = array();
        $profile = $this->default_package_profile($package);

        if ($profile) {
            $benefits = $profile['benefits'];
            if (!empty($package->required_assets)) {
                $benefits[] = __('Creative assets collected during fulfillment', 'vms-sponsorships');
            }
        } else {
            if ((int) $package->public_display_enabled) {
                $benefits[] = __('Public event-page recognition', 'vms-sponsorships');
            }

            if ((int) $package->email_display_enabled) {
                $benefits[] = __('Eligible for email/newsletter placement', 'vms-sponsorships');
            }

            if ((int) $package->includes_physical_banner) {
                $benefits[] = __('Limited physical banner opportunity, subject to approval', 'vms-sponsorships');
            }

            if ((int) $package->report_included) {
                $benefits[] = __('Post-event visibility summary', 'vms-sponsorships');
            }

            if (!empty($package->required_assets)) {
                $benefits[] = __('Creative assets collected during fulfillment', 'vms-sponsorships');
            }
        }

        $benefits = array_values(array_unique(array_filter(array_map('trim', $benefits))));

        return $benefits;
    }

    private function default_package_profile($package) {
        $slug = sanitize_title($package->slug ?? '');

        return VMS_Sponsorships_Install::default_package_profile($slug);
    }

    private function default_consideration_type_for_package($package) {
        $profile = $this->default_package_profile($package);
        if ($profile && !empty($profile['consideration_type'])) {
            return sanitize_key($profile['consideration_type']);
        }

        $haystack = strtolower(implode(' ', array(
            (string) ($package->name ?? ''),
            (string) ($package->slug ?? ''),
            trim(wp_strip_all_tags((string) ($package->description ?? ''))),
        )));

        $type = 'cash';

        if (false !== strpos($haystack, 'cash + trade') || false !== strpos($haystack, 'cash and trade')) {
            $type = 'cash_and_trade';
        } elseif (false !== strpos($haystack, 'trade') || false !== strpos($haystack, 'in-kind') || false !== strpos($haystack, 'in kind')) {
            $type = 'trade_in_kind';
        }

        return apply_filters('vms_sponsorships_public_package_consideration_type', $type, $package);
    }

    private function consideration_type_uses_trade_fields($type) {
        return in_array(sanitize_key($type), array('trade_in_kind', 'cash_and_trade'), true);
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

    private function scope_label($scope) {
        $labels = array(
            'event' => __('Event sponsorship', 'vms-sponsorships'),
            'feature' => __('Feature sponsorship', 'vms-sponsorships'),
            'season' => __('Season sponsorship', 'vms-sponsorships'),
            'venue' => __('Venue sponsorship', 'vms-sponsorships'),
            'newsletter' => __('Newsletter sponsorship', 'vms-sponsorships'),
        );

        return $labels[$scope] ?? ucwords(str_replace('_', ' ', $scope));
    }

    private function consideration_type_choices() {
        return array(
            'cash' => __('Cash sponsorship', 'vms-sponsorships'),
            'trade_in_kind' => __('Trade / in-kind sponsorship', 'vms-sponsorships'),
            'cash_and_trade' => __('Cash + trade combination', 'vms-sponsorships'),
            'not_sure' => __('Not sure yet', 'vms-sponsorships'),
        );
    }

    private function consideration_type_label($type) {
        $choices = $this->consideration_type_choices();

        return $choices[$type] ?? $choices['not_sure'];
    }

    private function current_request_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $request_uri = remove_query_arg(array('vms_sponsor_submitted', 'vms_sponsor_error', 'vms_sponsor_asset_uploaded'), $request_uri);

        return home_url($request_uri);
    }

    private function sanitize_submitted_package_snapshot() {
        $request = wp_unslash($_POST);

        return array(
            'selectedPackageName' => sanitize_text_field($request['selectedPackageName'] ?? ''),
            'selectedPackageValue' => sanitize_text_field($request['selectedPackageValue'] ?? ''),
            'selectedPackageType' => sanitize_key($request['selectedPackageType'] ?? ''),
            'sponsorshipPackageInterest' => sanitize_text_field($request['sponsorshipPackageInterest'] ?? ''),
            'sponsorshipConsiderationType' => sanitize_text_field($request['sponsorshipConsiderationType'] ?? ''),
        );
    }

    private function is_honeypot_tripped($request) {
        return '' !== trim((string) ($request[self::HONEYPOT_FIELD] ?? ''));
    }

    private function has_recent_application_submission($request) {
        $transient_key = $this->application_submission_transient_key($request);

        return $transient_key !== '' && (bool) get_transient($transient_key);
    }

    private function mark_recent_application_submission($request) {
        $transient_key = $this->application_submission_transient_key($request);
        if ($transient_key !== '') {
            set_transient($transient_key, 1, self::APPLICATION_DUPLICATE_WINDOW);
        }
    }

    private function application_submission_transient_key($request) {
        $email = strtolower(sanitize_email($request['email'] ?? ''));
        $business_name = strtolower(sanitize_text_field($request['business_name'] ?? ''));
        if ($email === '' || $business_name === '') {
            return '';
        }

        $fingerprint = array(
            $email,
            $business_name,
            absint($request['event_id'] ?? 0),
            absint($request['season_id'] ?? 0),
            absint($request['requested_package_id'] ?? 0),
            sanitize_key($request['requested_slot_key'] ?? ''),
            sanitize_key($request['scope'] ?? 'event'),
            $this->current_client_ip(),
        );

        return 'vms_sponsorships_apply_' . md5(wp_json_encode($fingerprint));
    }

    private function has_recent_asset_upload($file, $context) {
        $transient_key = $this->asset_upload_transient_key($file, $context);

        return $transient_key !== '' && (bool) get_transient($transient_key);
    }

    private function mark_recent_asset_upload($file, $context) {
        $transient_key = $this->asset_upload_transient_key($file, $context);
        if ($transient_key !== '') {
            set_transient($transient_key, 1, self::ASSET_DUPLICATE_WINDOW);
        }
    }

    private function asset_upload_transient_key($file, $context) {
        $filename = sanitize_file_name(wp_basename((string) ($file['name'] ?? '')));
        $filesize = isset($file['size']) ? absint($file['size']) : 0;

        if ($filename === '' || $filesize <= 0) {
            return '';
        }

        $fingerprint = array(
            get_current_user_id(),
            absint($context['assignment_id'] ?? 0),
            absint($context['application_id'] ?? 0),
            $filename,
            $filesize,
        );

        return 'vms_sponsorships_asset_' . md5(wp_json_encode($fingerprint));
    }

    private function resolve_asset_upload_context($assignment_id, $application_id) {
        $assignment_id = absint($assignment_id);
        $application_id = absint($application_id);
        $assignment = $assignment_id > 0 ? $this->repo->get_assignment($assignment_id) : null;
        $application = $application_id > 0 ? $this->repo->get_application($application_id) : null;

        if ($assignment_id > 0 && !$assignment) {
            return new WP_Error('missing_context', __('The requested sponsorship assignment was not found.', 'vms-sponsorships'));
        }

        if ($application_id > 0 && !$application) {
            return new WP_Error('missing_context', __('The requested sponsorship application was not found.', 'vms-sponsorships'));
        }

        if ($assignment && $application && !empty($assignment->application_id) && absint($assignment->application_id) !== absint($application->id)) {
            return new WP_Error('missing_context', __('The requested upload context is invalid.', 'vms-sponsorships'));
        }

        if ($assignment && !$application && !empty($assignment->application_id)) {
            $linked_application = $this->repo->get_application($assignment->application_id);
            if ($linked_application) {
                $application = $linked_application;
                $application_id = absint($linked_application->id);
            }
        }

        if ($assignment_id <= 0 && $application_id <= 0) {
            return new WP_Error('missing_context', __('A sponsorship assignment or application is required for uploads.', 'vms-sponsorships'));
        }

        return array(
            'assignment_id' => $assignment ? absint($assignment->id) : 0,
            'application_id' => $application ? absint($application->id) : 0,
            'assignment' => $assignment,
            'application' => $application,
        );
    }

    private function current_user_can_upload_for_context($assignment, $application) {
        if ($this->current_user_can_manage_sponsorships()) {
            return true;
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id <= 0) {
            return false;
        }

        $owner_ids = array_filter(array_unique(array(
            absint($assignment->user_id ?? 0),
            absint($application->user_id ?? 0),
        )));

        return in_array($current_user_id, $owner_ids, true);
    }

    private function current_user_can_manage_sponsorships() {
        return current_user_can(apply_filters('vms_sponsorships_manage_capability', 'manage_options'));
    }

    private function delete_uploaded_file($file) {
        if (is_string($file) && $file !== '' && file_exists($file)) {
            wp_delete_file($file);
        }
    }

    private function current_client_ip() {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';

        return preg_replace('/[^0-9a-fA-F:\\., ]/', '', $remote_addr);
    }

    private function get_query_var($key, $default = '') {
        return isset($_GET[$key]) ? (string) wp_unslash($_GET[$key]) : $default;
    }

    private function get_query_absint($key, $default = 0) {
        $value = $this->get_query_var($key, null);

        return null === $value ? absint($default) : absint($value);
    }

    private function get_query_key($key, $default = '') {
        $value = $this->get_query_var($key, null);

        return null === $value ? sanitize_key($default) : sanitize_key($value);
    }

    private function normalize_money_for_input($amount) {
        return number_format((float) $amount, 2, '.', '');
    }

    private function not_sure_label() {
        return __('Not sure yet', 'vms-sponsorships');
    }
}
