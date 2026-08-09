<?php
/**
 * Plugin Name:       WP Adverts <> ARMember
 * Plugin URI:        https://github.com/renatobo/wpadverts-armember
 * Description:       Protects WPAdverts listings and publishing surfaces with ARMember-aware access control.
 * Version:           0.4.1
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Requires Plugins:  wpadverts, armember-membership
 * Author:            Renato Bonomini
 * Author URI:        https://github.com/renatobo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpadverts-armember
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: https://github.com/renatobo/wpadverts-armember
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package WPAdverts_ARMember
 */

defined('ABSPATH') || exit;

define('WPAAG_VERSION', '0.4.1');
define('WPAAG_PLUGIN_FILE', __FILE__);
define('WPAAG_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once WPAAG_PLUGIN_DIR . 'includes/class-wpaag-plugin.php';

WPAAG_Plugin::instance()->register();
