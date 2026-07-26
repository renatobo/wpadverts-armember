<?php
/**
 * Plugin Name:       WPAdverts_ARMember
 * Plugin URI:        https://github.com/renatobo/wpadverts-armember
 * Description:       Protects WPAdverts listings and publishing surfaces with ARMember-aware access control.
 * Version:           0.3.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
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

define('WPAAG_VERSION', '0.3.0');
define('WPAAG_PLUGIN_FILE', __FILE__);
define('WPAAG_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once WPAAG_PLUGIN_DIR . 'includes/class-wpaag-plugin.php';

WPAAG_Plugin::instance()->register();
