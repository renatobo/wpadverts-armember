<?php

defined('ABSPATH') || exit;

final class WPAAG_Plugin {
    const OPTION_NAME = 'wpaag_settings';

    /**
     * @var WPAAG_Plugin|null
     */
    private static $instance = null;

    /**
     * @return WPAAG_Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register() {
        add_action('template_redirect', array($this, 'protect_frontend'), 1);
        add_filter('rest_pre_dispatch', array($this, 'protect_rest_requests'), 10, 3);

        if (is_admin()) {
            add_action('admin_menu', array($this, 'register_settings_page'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_notices', array($this, 'dependency_notice'));
            add_filter(
                'plugin_action_links_' . plugin_basename(WPAAG_PLUGIN_FILE),
                array($this, 'add_plugin_action_links')
            );
        }
    }

    /**
     * Protect WPAdverts frontend routes.
     *
     * @return void
     */
    public function protect_frontend() {
        if (!$this->is_protected_frontend_request() || $this->current_user_can_access()) {
            return;
        }

        $this->redirect_to_login();
    }

    /**
     * Protect REST endpoints that expose adverts.
     *
     * @param mixed           $result  Response to replace the requested version with.
     * @param WP_REST_Server  $server  REST server.
     * @param WP_REST_Request $request Request object.
     * @return mixed
     */
    public function protect_rest_requests($result, $server, $request) {
        unset($server);

        if (null !== $result || $this->current_user_can_access()) {
            return $result;
        }

        $route = $request->get_route();
        if (!$this->is_protected_rest_route($route)) {
            return $result;
        }

        return new WP_Error(
            'wpaag_access_denied',
            __('You must be an authorized member to access classifieds.', 'wpadverts-armember'),
            array('status' => 401)
        );
    }

    /**
     * Determine whether the current visitor may access classifieds.
     *
     * @return bool
     */
    public function current_user_can_access() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        $allowed = user_can($user_id, 'manage_options');

        if (!$allowed && function_exists('arm_get_member_status')) {
            $settings = $this->get_settings();
            if ('valid_plan' === $settings['access_mode']) {
                $allowed = $this->has_valid_membership($user_id);
            } else {
                $allowed = false !== arm_get_member_status($user_id);
            }
        }

        /**
         * Filter whether a user may access protected WPAdverts content.
         *
         * @param bool $allowed Access decision.
         * @param int  $user_id WordPress user ID.
         */
        return (bool) apply_filters('wpaag_user_can_access', $allowed, $user_id);
    }

    /**
     * Check for an active ARMember account with an effective plan.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    private function has_valid_membership($user_id) {
        if (!function_exists('arm_is_member_active') || !arm_is_member_active($user_id)) {
            return false;
        }

        $plans = get_user_meta($user_id, 'arm_user_plan_ids', true);
        $plans = is_array($plans) ? $plans : array();
        $plans = apply_filters('arm_allow_specific_user_restricted_access', $plans, $user_id);
        $plans = is_array($plans) ? $plans : array();

        $suspended = get_user_meta($user_id, 'arm_user_suspended_plan_ids', true);
        $suspended = apply_filters('arm_assign_suspended_plan_data', $suspended, $user_id);
        if (is_array($suspended) && !empty($suspended)) {
            $plans = array_values(array_diff($plans, $suspended));
        }

        return !empty($plans);
    }

    /**
     * Identify WPAdverts frontend surfaces.
     *
     * @return bool
     */
    private function is_protected_frontend_request() {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }

        if (is_singular('advert') || is_post_type_archive('advert') || is_tax('advert_category')) {
            return true;
        }

        if (!is_singular('page')) {
            return false;
        }

        $post = get_queried_object();
        if (!($post instanceof WP_Post)) {
            return false;
        }

        $protected_blocks = array(
            'wpadverts/categories',
            'wpadverts/list',
            'wpadverts/manage',
            'wpadverts/publish',
            'wpadverts/search',
        );

        foreach ($protected_blocks as $block_name) {
            if (has_block($block_name, $post)) {
                return true;
            }
        }

        $protected_shortcodes = array(
            'advert_single',
            'adverts_add',
            'adverts_block',
            'adverts_categories',
            'adverts_list',
            'adverts_manage',
            'adverts_payments_checkout',
        );

        foreach ($protected_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        /**
         * Filter whether a frontend request is a protected WPAdverts surface.
         *
         * @param bool    $protected Default decision.
         * @param WP_Post $post      Current page.
         */
        return (bool) apply_filters('wpaag_is_protected_page', false, $post);
    }

    /**
     * Identify REST routes that can expose adverts.
     *
     * @param string $route REST route.
     * @return bool
     */
    private function is_protected_rest_route($route) {
        $prefixes = array(
            '/wp/v2/advert',
            '/wp/v2/advert_category',
            '/wpadverts/',
        );

        foreach ($prefixes as $prefix) {
            if (0 === strpos($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect unauthorized visitors to the configured login page.
     *
     * @return void
     */
    private function redirect_to_login() {
        $settings = $this->get_settings();
        $target   = home_url('/');

        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
            $target      = home_url('/' . ltrim($request_uri, '/'));
        }

        $destination_url = '';
        $destination_id  = absint($settings['destination_page_id']);

        if ($destination_id && 'publish' === get_post_status($destination_id)) {
            $destination_url = get_permalink($destination_id);
        }

        if (!$destination_url) {
            $destination_url = wp_login_url();
        }

        $destination_url = add_query_arg('redirect_to', $target, $destination_url);
        wp_safe_redirect($destination_url, 302, 'WPAdverts_ARMember');
        exit;
    }

    /**
     * Register the settings page.
     *
     * @return void
     */
    public function register_settings_page() {
        add_options_page(
            __('WPAdverts_ARMember', 'wpadverts-armember'),
            __('WPAdverts_ARMember', 'wpadverts-armember'),
            'manage_options',
            'wpaag-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue settings-page styles only where needed.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ('settings_page_wpaag-settings' !== $hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'wpaag-admin-settings',
            plugins_url('assets/css/admin-settings.css', WPAAG_PLUGIN_FILE),
            array(),
            WPAAG_VERSION
        );
    }

    /**
     * Add a Settings link to the Plugins screen.
     *
     * @param array<int,string> $links Existing action links.
     * @return array<int,string>
     */
    public function add_plugin_action_links($links) {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('options-general.php?page=wpaag-settings')),
                esc_html__('Settings', 'wpadverts-armember')
            )
        );

        return $links;
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'wpaag_settings_group',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => $this->get_default_settings(),
            )
        );
    }

    /**
     * Sanitize plugin settings.
     *
     * @param mixed $input Raw settings.
     * @return array
     */
    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $mode  = isset($input['access_mode']) ? sanitize_key($input['access_mode']) : 'recognized_user';

        if (!in_array($mode, array('recognized_user', 'valid_plan'), true)) {
            $mode = 'recognized_user';
        }

        return array(
            'access_mode'        => $mode,
            'destination_page_id' => isset($input['destination_page_id']) ? absint($input['destination_page_id']) : 0,
        );
    }

    /**
     * Render settings.
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings          = $this->get_settings();
        $project_url       = 'https://github.com/renatobo/wpadverts-armember';
        $release_notes_url = $project_url . '/releases/tag/v' . rawurlencode(WPAAG_VERSION);
        $author_url        = 'https://github.com/renatobo';
        $git_updater_url   = 'https://github.com/afragen/git-updater';
        $banner_url        = plugins_url('assets/wpadverts-armember-settings-banner.svg', WPAAG_PLUGIN_FILE);
        ?>
        <div class="wrap">
            <div class="wpaag-admin">
                <div class="wpaag-hero">
                    <img
                        src="<?php echo esc_url($banner_url); ?>"
                        alt="<?php echo esc_attr__('WPAdverts_ARMember settings banner', 'wpadverts-armember'); ?>"
                        class="wpaag-hero-image"
                    />
                </div>

                <div class="wpaag-meta">
                    <a href="<?php echo esc_url($project_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('GitHub Repository', 'wpadverts-armember'); ?>
                    </a>
                    <span>
                        <?php
                        /* translators: %s: Plugin version. */
                        echo esc_html(sprintf(__('Version %s', 'wpadverts-armember'), WPAAG_VERSION));
                        ?>
                    </span>
                    <a href="<?php echo esc_url($release_notes_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Release notes', 'wpadverts-armember'); ?>
                    </a>
                    <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Renato Bonomini on GitHub', 'wpadverts-armember'); ?>
                    </a>
                    <a href="<?php echo esc_url($git_updater_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('GitHub updates via Git Updater', 'wpadverts-armember'); ?>
                    </a>
                </div>

                <div class="wpaag-headline">
                    <h1><?php esc_html_e('WPAdverts_ARMember Settings', 'wpadverts-armember'); ?></h1>
                    <p>
                        <?php esc_html_e('Protect WPAdverts listings, categories, publishing pages, and REST data with ARMember-aware access rules.', 'wpadverts-armember'); ?>
                    </p>
                    <p>
                        <?php esc_html_e('GitHub Releases is the distribution channel for packaged installs and dashboard updates through Git Updater.', 'wpadverts-armember'); ?>
                    </p>
                </div>

                <?php settings_errors(); ?>

                <form method="post" action="options.php" class="wpaag-shell">
                <?php settings_fields('wpaag_settings_group'); ?>
                    <section class="wpaag-card wpaag-card-accent">
                        <div class="wpaag-field">
                            <div>
                                <label for="wpaag-access-mode"><?php esc_html_e('Access requirement', 'wpadverts-armember'); ?></label>
                                <p><?php esc_html_e('Choose how ARMember determines who can access protected classifieds.', 'wpadverts-armember'); ?></p>
                            </div>
                            <select id="wpaag-access-mode" name="<?php echo esc_attr(self::OPTION_NAME); ?>[access_mode]">
                                <option value="recognized_user" <?php selected($settings['access_mode'], 'recognized_user'); ?>>
                                    <?php esc_html_e('Recognized ARMember user', 'wpadverts-armember'); ?>
                                </option>
                                <option value="valid_plan" <?php selected($settings['access_mode'], 'valid_plan'); ?>>
                                    <?php esc_html_e('Active ARMember account with a valid plan', 'wpadverts-armember'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="wpaag-field">
                            <div>
                                <label for="wpaag-destination-page"><?php esc_html_e('Unauthorized visitor destination', 'wpadverts-armember'); ?></label>
                                <p><?php esc_html_e('Select the page shown to visitors who do not meet the access requirement.', 'wpadverts-armember'); ?></p>
                            </div>
                            <?php
                            wp_dropdown_pages(
                                array(
                                    'id'               => 'wpaag-destination-page',
                                    'name'             => self::OPTION_NAME . '[destination_page_id]',
                                    'selected'         => absint($settings['destination_page_id']),
                                    'show_option_none' => __('WordPress login page', 'wpadverts-armember'),
                                    'option_none_value' => '0',
                                )
                            );
                            ?>
                        </div>

                        <div class="wpaag-note">
                            <strong><?php esc_html_e('Protected surfaces', 'wpadverts-armember'); ?></strong>
                            <span><?php esc_html_e('Single adverts, advert archives, advert categories, WPAdverts blocks and shortcodes, publishing and management pages, and advert REST routes.', 'wpadverts-armember'); ?></span>
                        </div>
                    </section>

                    <div class="wpaag-footer">
                        <?php submit_button(__('Save settings', 'wpadverts-armember'), 'primary', 'submit', false); ?>
                        <span><?php esc_html_e('Administrators always retain access.', 'wpadverts-armember'); ?></span>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Show dependency status to administrators.
     *
     * @return void
     */
    public function dependency_notice() {
        if (!current_user_can('manage_options') || function_exists('arm_get_member_status')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        esc_html_e('WPAdverts_ARMember is active, but ARMember is unavailable. Only administrators can access protected classifieds.', 'wpadverts-armember');
        echo '</p></div>';
    }

    /**
     * Get sanitized settings.
     *
     * @return array
     */
    private function get_settings() {
        $settings = get_option(self::OPTION_NAME, array());
        $settings = is_array($settings) ? $settings : array();

        if (!isset($settings['destination_page_id']) && !empty($settings['login_url'])) {
            $settings['destination_page_id'] = url_to_postid($settings['login_url']);
        }

        return wp_parse_args($settings, $this->get_default_settings());
    }

    /**
     * @return array
     */
    private function get_default_settings() {
        $login_page = get_page_by_path('login-2');

        return array(
            'access_mode'         => 'recognized_user',
            'destination_page_id' => $login_page ? (int) $login_page->ID : 0,
        );
    }

    private function __construct() {
    }
}
