<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Notifications {
    /** @var VMS_Sponsorships_Repository */
    private $repo;

    public function __construct(VMS_Sponsorships_Repository $repo) {
        $this->repo = $repo;
    }

    public function new_application($application_id) {
        $application = $this->repo->get_application($application_id);
        if (!$application) {
            return false;
        }

        $subject = sprintf(__('New sponsorship application: %s', 'vms-sponsorships'), $application->business_name);
        $message = $this->replace_tokens(
            "A new sponsorship application has been submitted.\n\nBusiness: {business_name}\nContact: {contact_name}\nEmail: {email}\nEvent ID: {event_id}\nScope: {scope}\nSlot: {slot}\nPackage ID: {package_id}\nConsideration: {consideration_type}\nEstimated trade value: {estimated_trade_value}\nTrade / in-kind offer details: {trade_offer_details}\n\nReview it in VMS Sponsorships.",
            $application
        );

        wp_mail(get_option('admin_email'), $subject, $message);

        do_action('vms_sponsorships_operator_notification', array(
            'type' => 'new_application',
            'application_id' => $application_id,
            'message' => $subject,
        ));

        $this->send_application_received($application);
        return true;
    }

    public function send_application_received($application) {
        if (empty($application->email) || !is_email($application->email)) {
            return false;
        }

        $subject = sprintf(__('We received your sponsorship application for %s', 'vms-sponsorships'), get_bloginfo('name'));
        $message = $this->replace_tokens(
            "Hi {contact_name},\n\nThank you for your interest in becoming a sponsor. We received your application for {business_name} and will review it soon.\n\nThank you,\n{venue_name}",
            $application
        );

        return wp_mail($application->email, $subject, $message);
    }

    public function application_status_changed($application) {
        if (empty($application->email) || !is_email($application->email)) {
            return false;
        }

        $status = sanitize_key($application->status);
        $subject = '';
        $message = '';

        if ('approved' === $status) {
            $subject = sprintf(__('Sponsorship application approved: %s', 'vms-sponsorships'), $application->business_name);
            $message = "Hi {contact_name},\n\nYour sponsorship application for {business_name} has been approved. We will follow up with next steps, payment instructions, and asset upload details.\n\nThank you,\n{venue_name}";
        } elseif ('declined' === $status) {
            $subject = sprintf(__('Sponsorship application update: %s', 'vms-sponsorships'), $application->business_name);
            $message = "Hi {contact_name},\n\nThank you for your interest in sponsoring {venue_name}. At this time, we are not moving forward with this sponsorship opportunity.\n\nThank you,\n{venue_name}";
        } elseif ('waitlisted' === $status) {
            $subject = sprintf(__('Sponsorship application waitlisted: %s', 'vms-sponsorships'), $application->business_name);
            $message = "Hi {contact_name},\n\nThank you for your interest in sponsoring {venue_name}. We have added your application to our waitlist and may follow up about future opportunities.\n\nThank you,\n{venue_name}";
        } else {
            $subject = sprintf(__('Sponsorship application status updated: %s', 'vms-sponsorships'), $application->business_name);
            $message = "Hi {contact_name},\n\nYour sponsorship application status has been updated to: {status}.\n\nThank you,\n{venue_name}";
        }

        do_action('vms_sponsorships_sponsor_notification', array(
            'type' => 'application_status_changed',
            'application_id' => $application->id,
            'status' => $status,
            'message' => $subject,
        ));

        return wp_mail($application->email, $subject, $this->replace_tokens($message, $application));
    }

    public function asset_uploaded($asset_id, $assignment_id = 0) {
        $subject = __('Sponsor asset uploaded for review', 'vms-sponsorships');
        $message = sprintf(
            "A sponsor asset has been uploaded and needs review.\n\nAsset ID: %d\nAssignment ID: %d",
            absint($asset_id),
            absint($assignment_id)
        );

        wp_mail(get_option('admin_email'), $subject, $message);

        do_action('vms_sponsorships_operator_notification', array(
            'type' => 'asset_uploaded',
            'asset_id' => $asset_id,
            'assignment_id' => $assignment_id,
            'message' => $subject,
        ));
    }

    public function asset_status_changed($asset) {
        if (!$asset) {
            return false;
        }

        $status = sanitize_key($asset->status ?? '');
        if (!in_array($status, array('approved', 'rejected', 'needs_revision'), true)) {
            return false;
        }

        $context = $this->asset_notification_context($asset);
        if (empty($context['email']) || !is_email($context['email'])) {
            return false;
        }

        $subject = '';
        $message = '';

        if ('approved' === $status) {
            $subject = sprintf(__('Sponsor asset approved: %s', 'vms-sponsorships'), $context['business_name']);
            $message = "Hi {contact_name},\n\nYour {asset_type} for {business_name} has been approved and can now be used in sponsorship placements.\n\nThank you,\n{venue_name}";
        } elseif ('rejected' === $status) {
            $subject = sprintf(__('Sponsor asset update: %s', 'vms-sponsorships'), $context['business_name']);
            $message = "Hi {contact_name},\n\nWe reviewed the {asset_type} for {business_name} and it cannot be used as submitted. Please reply or upload a replacement asset so we can continue the review.\n\nThank you,\n{venue_name}";
        } else {
            $subject = sprintf(__('Sponsor asset needs revision: %s', 'vms-sponsorships'), $context['business_name']);
            $message = "Hi {contact_name},\n\nWe reviewed the {asset_type} for {business_name} and need a revised version before it can be published. Please reply or upload an updated asset when ready.\n\nThank you,\n{venue_name}";
        }

        do_action('vms_sponsorships_sponsor_notification', array(
            'type' => 'asset_status_changed',
            'asset_id' => $asset->id,
            'assignment_id' => $asset->assignment_id ?? 0,
            'status' => $status,
            'message' => $subject,
        ));

        return wp_mail($context['email'], $subject, $this->replace_asset_tokens($message, $context));
    }

    private function replace_tokens($template, $application) {
        $tokens = array(
            '{business_name}' => $application->business_name ?? '',
            '{contact_name}' => !empty($application->contact_name) ? $application->contact_name : ($application->business_name ?? ''),
            '{email}' => $application->email ?? '',
            '{event_id}' => $application->event_id ?? '',
            '{scope}' => $application->scope ?? '',
            '{slot}' => $application->requested_slot_key ?? '',
            '{package_id}' => $application->requested_package_id ?? '',
            '{consideration_type}' => $this->consideration_type_label($application->consideration_type ?? 'not_sure'),
            '{estimated_trade_value}' => $this->format_money($application->estimated_trade_value ?? null),
            '{trade_offer_details}' => !empty($application->trade_offer_details) ? $application->trade_offer_details : __('None provided', 'vms-sponsorships'),
            '{status}' => $application->status ?? '',
            '{venue_name}' => get_bloginfo('name'),
        );

        return strtr($template, $tokens);
    }

    private function replace_asset_tokens($template, $context) {
        $tokens = array(
            '{business_name}' => $context['business_name'],
            '{contact_name}' => $context['contact_name'],
            '{asset_type}' => $context['asset_type'],
            '{venue_name}' => get_bloginfo('name'),
        );

        return strtr($template, $tokens);
    }

    private function asset_notification_context($asset) {
        $assignment = !empty($asset->assignment_id) ? $this->repo->get_assignment($asset->assignment_id) : null;
        $application = !empty($asset->application_id) ? $this->repo->get_application($asset->application_id) : null;

        if (!$application && $assignment && !empty($assignment->application_id)) {
            $application = $this->repo->get_application($assignment->application_id);
        }

        $email = $application->email ?? '';
        $contact_name = $application->contact_name ?? '';

        if ((!$email || !is_email($email)) && !empty($asset->user_id)) {
            $user = get_userdata((int) $asset->user_id);
            if ($user && is_email($user->user_email)) {
                $email = $user->user_email;
                $contact_name = $contact_name ?: $user->display_name;
            }
        }

        if ((!$email || !is_email($email)) && $assignment && !empty($assignment->user_id)) {
            $user = get_userdata((int) $assignment->user_id);
            if ($user && is_email($user->user_email)) {
                $email = $user->user_email;
                $contact_name = $contact_name ?: $user->display_name;
            }
        }

        $business_name = $application->business_name ?? ($assignment->sponsor_display_name ?? __('your sponsorship', 'vms-sponsorships'));

        return array(
            'assignment' => $assignment,
            'application' => $application,
            'email' => $email,
            'contact_name' => $contact_name ?: $business_name,
            'business_name' => $business_name,
            'asset_type' => $this->asset_type_label($asset->asset_type ?? 'asset'),
        );
    }

    private function asset_type_label($asset_type) {
        $asset_type = sanitize_key($asset_type);
        return strtolower(str_replace('_', ' ', $asset_type));
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

    private function format_money($amount) {
        if (null === $amount || '' === (string) $amount) {
            return __('Not provided', 'vms-sponsorships');
        }

        return '$' . number_format_i18n((float) $amount, 2);
    }
}
