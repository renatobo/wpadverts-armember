<?php
/**
 * Minimal WordPress and ARMember stand-ins for unit tests.
 *
 * Each stub reads from WPAAG_Test_State so a test can describe the site,
 * the current user, and ARMember's view of that user without a database.
 *
 * @package WPAdverts_ARMember
 */

define('ABSPATH', __DIR__ . '/');
define('WPAAG_PLUGIN_FILE', dirname(__DIR__) . '/wpadverts-armember.php');
define('WPAAG_VERSION', 'test');

final class WPAAG_Test_State {
    public static int $user_id = 0;
    public static array $admins = array();
    public static array $arm_status = array();
    public static array $user_meta = array();
    public static array $options = array();
    public static array $posts = array();
    public static array $comments = array();
    public static array $query_vars = array();
    public static array $conditionals = array();
    public static array $filters = array();
    public static array $edit_caps = array();
    public static string $home_url = 'https://example.test';

    public static function reset(): void {
        self::$user_id      = 0;
        self::$admins       = array();
        self::$arm_status   = array();
        self::$user_meta    = array();
        self::$options      = array();
        self::$posts        = array();
        self::$comments     = array();
        self::$query_vars   = array();
        self::$conditionals = array();
        self::$filters      = array();
        self::$edit_caps    = array();
        self::$home_url     = 'https://example.test';
        unset($_SERVER['REQUEST_URI']);
    }
}

class WP_Error {
    public function __construct(public string $code = '', public string $message = '', public mixed $data = null) {
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_data() {
        return $this->data;
    }
}

class WP_Post {
    public int $ID = 0;
    public string $post_type = 'post';
    public int $post_author = 0;
    public int $post_parent = 0;
    public string $post_content = '';

    public function __construct(array $fields = array()) {
        foreach ($fields as $key => $value) {
            $this->$key = $value;
        }
    }
}

class WP_REST_Request {
    public function __construct(private string $route, private array $params = array()) {
    }

    public function get_route() {
        return $this->route;
    }

    public function get_param($key) {
        return $this->params[$key] ?? null;
    }
}

function __($text, $domain = 'default') {
    return $text;
}

function esc_html__($text, $domain = 'default') {
    return $text;
}

function absint($value) {
    return abs((int) $value);
}

function wp_parse_args($args, $defaults = array()) {
    return array_merge($defaults, (array) $args);
}

function wp_unslash($value) {
    return $value;
}

function untrailingslashit($value) {
    return rtrim($value, '/\\');
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

function home_url($path = '') {
    return WPAAG_Test_State::$home_url . '/' . ltrim($path, '/');
}

function apply_filters($hook, $value, ...$args) {
    if (isset(WPAAG_Test_State::$filters[$hook])) {
        return (WPAAG_Test_State::$filters[$hook])($value, ...$args);
    }

    return $value;
}

function get_option($name, $default = false) {
    return WPAAG_Test_State::$options[$name] ?? $default;
}

function get_current_user_id() {
    return WPAAG_Test_State::$user_id;
}

function is_user_logged_in() {
    return WPAAG_Test_State::$user_id > 0;
}

function user_can($user_id, $capability) {
    return 'manage_options' === $capability && in_array($user_id, WPAAG_Test_State::$admins, true);
}

function current_user_can($capability, ...$args) {
    if ('edit_post' === $capability) {
        return in_array((int) ($args[0] ?? 0), WPAAG_Test_State::$edit_caps, true);
    }

    return user_can(WPAAG_Test_State::$user_id, $capability);
}

function get_user_meta($user_id, $key, $single = false) {
    return WPAAG_Test_State::$user_meta[$user_id][$key] ?? '';
}

function get_post($post_id) {
    return WPAAG_Test_State::$posts[$post_id] ?? null;
}

function get_post_type($post_id) {
    $post = get_post((int) $post_id);

    return $post ? $post->post_type : false;
}

function get_post_field($field, $post_id) {
    $post = get_post((int) $post_id);

    return $post ? $post->$field : '';
}

function wp_get_post_parent_id($post_id) {
    $post = get_post((int) $post_id);

    return $post ? $post->post_parent : false;
}

function get_comment($comment_id) {
    return WPAAG_Test_State::$comments[$comment_id] ?? null;
}

function get_query_var($name, $default = '') {
    return WPAAG_Test_State::$query_vars[$name] ?? $default;
}

function get_queried_object() {
    return WPAAG_Test_State::$query_vars['queried_object'] ?? null;
}

function is_admin() {
    return false;
}

function wp_doing_ajax() {
    return false;
}

function is_singular($post_types = '') {
    return in_array('singular:' . $post_types, WPAAG_Test_State::$conditionals, true);
}

function is_post_type_archive($post_types = '') {
    return false;
}

function is_tax($taxonomy = '') {
    return false;
}

function is_attachment() {
    return in_array('attachment', WPAAG_Test_State::$conditionals, true);
}

// ARMember Lite: primary status 1 means active.
function arm_is_member_active($user_id) {
    return '1' === (string) (WPAAG_Test_State::$arm_status[$user_id] ?? '');
}

require dirname(__DIR__) . '/includes/class-wpaag-plugin.php';
