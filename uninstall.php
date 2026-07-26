<?php
/**
 * Remove plugin-owned settings.
 *
 * @package WPAdverts_ARMember
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('wpaag_settings');

if (is_multisite()) {
    delete_site_option('wpaag_settings');
}
