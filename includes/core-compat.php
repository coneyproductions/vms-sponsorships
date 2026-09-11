<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve current BVM providers before historical VMS providers.
 */
function vms_sponsorships_core_function(string $legacy_function): string
{
    $canonical_function = preg_replace('/^vms_/', 'bvmgr_', $legacy_function);
    if (is_string($canonical_function) && function_exists($canonical_function)) {
        return $canonical_function;
    }
    if (function_exists($legacy_function)) {
        return $legacy_function;
    }

    return '';
}
