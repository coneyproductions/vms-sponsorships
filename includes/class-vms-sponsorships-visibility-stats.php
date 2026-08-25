<?php

if (!defined('ABSPATH')) {
    exit;
}

class VMS_Sponsorships_Visibility_Stats {
    private const LAST_SAVED_OPTION = 'vms_sponsorships_visibility_last_saved_at';
    private const REVIEW_SOON_AFTER_DAYS = 21;
    private const STALE_AFTER_DAYS = 30;

    public static function save_from_request($request) {
        $defaults = VMS_Sponsorships_Install::default_visibility_settings();

        update_option('vms_sponsorships_visibility_title', sanitize_text_field($request['visibility_title'] ?? $defaults['vms_sponsorships_visibility_title']));
        update_option('vms_sponsorships_visibility_intro', sanitize_textarea_field($request['visibility_intro'] ?? $defaults['vms_sponsorships_visibility_intro']));
        update_option('vms_sponsorships_visibility_website', sanitize_textarea_field($request['visibility_website'] ?? ''));
        update_option('vms_sponsorships_visibility_facebook', sanitize_text_field($request['visibility_facebook'] ?? ''));
        update_option('vms_sponsorships_visibility_instagram', sanitize_text_field($request['visibility_instagram'] ?? ''));
        update_option('vms_sponsorships_visibility_email', sanitize_text_field($request['visibility_email'] ?? ''));
        update_option('vms_sponsorships_visibility_attendance', sanitize_textarea_field($request['visibility_attendance'] ?? ''));
        update_option('vms_sponsorships_visibility_custom_note', sanitize_textarea_field($request['visibility_custom_note'] ?? ''));
        update_option('vms_sponsorships_visibility_internal_notes', sanitize_textarea_field($request['visibility_internal_notes'] ?? ''));
        update_option('vms_sponsorships_visibility_last_updated', self::sanitize_public_date($request['visibility_last_updated'] ?? ''));
        update_option(self::LAST_SAVED_OPTION, time());
    }

    public static function load() {
        $defaults = VMS_Sponsorships_Install::default_visibility_settings();
        $last_saved_at = absint(get_option(self::LAST_SAVED_OPTION, 0));
        $public_last_updated_raw = trim((string) get_option('vms_sponsorships_visibility_last_updated', $defaults['vms_sponsorships_visibility_last_updated']));
        $public_last_updated_timestamp = self::parse_public_date($public_last_updated_raw);
        $has_saved_reference = $last_saved_at > 0 || $public_last_updated_timestamp > 0;

        $stats = array();
        foreach (self::stat_definitions() as $definition) {
            $value = trim((string) get_option($definition['option'], $defaults[$definition['option']] ?? ''));

            if (!$has_saved_reference && self::is_legacy_placeholder($definition['option'], $value)) {
                $value = '';
            }

            if ($value === '') {
                continue;
            }

            $stats[] = array(
                'key' => $definition['key'],
                'label' => $definition['label'],
                'value' => $value,
            );
        }

        $has_stats = !empty($stats);
        $freshness_timestamp = $last_saved_at > 0 ? $last_saved_at : $public_last_updated_timestamp;

        return array_merge(
            self::freshness_summary($has_stats, $freshness_timestamp, $public_last_updated_timestamp),
            array(
                'title' => trim((string) get_option('vms_sponsorships_visibility_title', $defaults['vms_sponsorships_visibility_title'])),
                'intro' => trim((string) get_option('vms_sponsorships_visibility_intro', $defaults['vms_sponsorships_visibility_intro'])),
                'stats' => $stats,
                'stat_count' => count($stats),
                'has_stats' => $has_stats,
                'last_saved_at' => $last_saved_at,
                'last_saved_display' => $last_saved_at > 0 ? wp_date(self::date_time_format(), $last_saved_at) : '',
                'public_last_updated_raw' => $public_last_updated_raw,
                'public_last_updated_timestamp' => $public_last_updated_timestamp,
                'public_last_updated_display' => $public_last_updated_timestamp > 0 ? wp_date(get_option('date_format'), $public_last_updated_timestamp) : '',
                'public_last_updated_text' => $public_last_updated_timestamp > 0
                    ? sprintf(__('As of %s', 'vms-sponsorships'), wp_date(get_option('date_format'), $public_last_updated_timestamp))
                    : '',
                'internal_notes' => trim((string) get_option('vms_sponsorships_visibility_internal_notes', $defaults['vms_sponsorships_visibility_internal_notes'])),
            )
        );
    }

    private static function stat_definitions() {
        return array(
            array(
                'key' => 'facebook',
                'option' => 'vms_sponsorships_visibility_facebook',
                'label' => __('Facebook audience', 'vms-sponsorships'),
            ),
            array(
                'key' => 'instagram',
                'option' => 'vms_sponsorships_visibility_instagram',
                'label' => __('Instagram audience', 'vms-sponsorships'),
            ),
            array(
                'key' => 'email',
                'option' => 'vms_sponsorships_visibility_email',
                'label' => __('Email list', 'vms-sponsorships'),
            ),
            array(
                'key' => 'website',
                'option' => 'vms_sponsorships_visibility_website',
                'label' => __('Website / event page visibility', 'vms-sponsorships'),
            ),
            array(
                'key' => 'attendance',
                'option' => 'vms_sponsorships_visibility_attendance',
                'label' => __('Typical attendance', 'vms-sponsorships'),
            ),
            array(
                'key' => 'custom_note',
                'option' => 'vms_sponsorships_visibility_custom_note',
                'label' => __('Sponsor note', 'vms-sponsorships'),
            ),
        );
    }

    private static function freshness_summary($has_stats, $freshness_timestamp, $public_last_updated_timestamp) {
        if (!$has_stats) {
            return array(
                'label' => __('Not configured', 'vms-sponsorships'),
                'tone' => 'neutral',
                'detail' => __('Visibility stats have not been configured yet.', 'vms-sponsorships'),
                'attention_message' => __('Add the sponsor-facing proof points you want proposals and inquiry pages to show.', 'vms-sponsorships'),
            );
        }

        if ($freshness_timestamp <= 0) {
            $detail = __('Sponsor visibility proof points exist, but their freshness date is missing. Save this page to start freshness tracking.', 'vms-sponsorships');
            if ($public_last_updated_timestamp > 0) {
                $detail = sprintf(
                    __('Public proof points are marked as of %s, but their freshness save date is missing. Save this page to start freshness tracking.', 'vms-sponsorships'),
                    wp_date(get_option('date_format'), $public_last_updated_timestamp)
                );
            }

            return array(
                'label' => __('Needs review soon', 'vms-sponsorships'),
                'tone' => 'attention',
                'detail' => $detail,
                'attention_message' => __('Update monthly or before sending sponsor proposals.', 'vms-sponsorships'),
            );
        }

        $days_old = max(0, (int) floor((time() - $freshness_timestamp) / DAY_IN_SECONDS));
        $public_detail = $public_last_updated_timestamp > 0
            ? sprintf(__('Public proof points marked as of %s.', 'vms-sponsorships'), wp_date(get_option('date_format'), $public_last_updated_timestamp))
            : __('Public "As of" date is not set yet.', 'vms-sponsorships');

        if ($days_old >= self::STALE_AFTER_DAYS) {
            return array(
                'label' => __('Stale', 'vms-sponsorships'),
                'tone' => 'warning',
                'detail' => sprintf(
                    __('Saved %1$s (%2$d days ago). %3$s', 'vms-sponsorships'),
                    wp_date(self::date_time_format(), $freshness_timestamp),
                    $days_old,
                    $public_detail
                ),
                'attention_message' => __('These proof points are stale. Update them before sending the next sponsor proposal.', 'vms-sponsorships'),
            );
        }

        if ($days_old >= self::REVIEW_SOON_AFTER_DAYS) {
            return array(
                'label' => __('Needs review soon', 'vms-sponsorships'),
                'tone' => 'attention',
                'detail' => sprintf(
                    __('Saved %1$s (%2$d days ago). %3$s', 'vms-sponsorships'),
                    wp_date(self::date_time_format(), $freshness_timestamp),
                    $days_old,
                    $public_detail
                ),
                'attention_message' => __('Review these proof points soon so sponsor proposals stay current.', 'vms-sponsorships'),
            );
        }

        return array(
            'label' => __('Updated recently', 'vms-sponsorships'),
            'tone' => 'positive',
            'detail' => sprintf(
                __('Saved %1$s (%2$d days ago). %3$s', 'vms-sponsorships'),
                wp_date(self::date_time_format(), $freshness_timestamp),
                $days_old,
                $public_detail
            ),
            'attention_message' => __('Update monthly or before sending sponsor proposals.', 'vms-sponsorships'),
        );
    }

    private static function sanitize_public_date($value) {
        $value = sanitize_text_field($value);

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return self::parse_public_date($value) > 0 ? $value : '';
    }

    private static function parse_public_date($value) {
        if (!is_string($value) || $value === '') {
            return 0;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 0;
        }

        $timestamp = 0;
        if (function_exists('wp_timezone')) {
            $date = date_create_immutable_from_format('Y-m-d H:i:s', $value . ' 00:00:00', wp_timezone());
            if ($date instanceof DateTimeImmutable) {
                $timestamp = $date->getTimestamp();
            }
        }

        if ($timestamp <= 0) {
            $timestamp = strtotime($value . ' 00:00:00');
        }

        return $timestamp ? (int) $timestamp : 0;
    }

    private static function is_legacy_placeholder($option_name, $value) {
        $placeholders = array(
            'vms_sponsorships_visibility_website' => __('Featured event pages, sponsor landing pages, and venue marketing touchpoints.', 'vms-sponsorships'),
            'vms_sponsorships_visibility_attendance' => __('Local East Texas live music fans, families, and community supporters across the event calendar.', 'vms-sponsorships'),
        );

        return isset($placeholders[$option_name]) && $value === $placeholders[$option_name];
    }

    private static function date_time_format() {
        $date_format = (string) get_option('date_format');
        $time_format = (string) get_option('time_format');

        $format = trim($date_format . ' ' . $time_format);

        return $format !== '' ? $format : 'F j, Y g:i a';
    }
}
