<?php

defined('ABSPATH') || exit;

final class WPAAG_Plugin {
    const OPTION_NAME = 'wpaag_settings';

    /**
     * WPAdverts blocks that expose classifieds.
     */
    const PROTECTED_BLOCKS = array(
        'wpadverts/categories',
        'wpadverts/list',
        'wpadverts/manage',
        'wpadverts/publish',
        'wpadverts/search',
    );

    /**
     * WPAdverts admin-ajax actions that expose or modify classifieds.
     *
     * These run through admin-ajax.php, which bypasses template_redirect and
     * the block and shortcode render guards.
     */
    const PROTECTED_AJAX_ACTIONS = array(
        'adext_payments_complete_payment',
        'adext_payments_render',
        'adverts_delete',
        'adverts_delete_tmp',
        'adverts_delete_tmp_files',
        'adverts_gallery_delete',
        'adverts_gallery_delete_file',
        'adverts_gallery_image_restore',
        'adverts_gallery_image_save',
        'adverts_gallery_image_stream',
        'adverts_gallery_update',
        'adverts_gallery_update_order',
        'adverts_gallery_upload',
        'adverts_gallery_video_cover',
        'adverts_show_contact',
        'wpadverts-contact-form-submit',
        'wpadverts-taxonomy',
    );

    /**
     * WPAdverts shortcodes that expose classifieds.
     */
    const PROTECTED_SHORTCODES = array(
        'advert_single',
        'adverts_add',
        'adverts_block',
        'adverts_categories',
        'adverts_list',
        'adverts_manage',
        'adverts_payments_checkout',
    );

    /**
     * @var WPAAG_Plugin|null
     */
    private static $instance = null;

    /**
     * @var array<int,bool>
     */
    private $access_cache = array();

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
        add_action('pre_get_posts', array($this, 'exclude_adverts_from_search'), 20);
        add_filter('render_block', array($this, 'protect_rendered_blocks'), 10, 2);
        add_filter('do_shortcode_tag', array($this, 'protect_rendered_shortcodes'), 10, 2);
        add_filter('rest_pre_dispatch', array($this, 'protect_rest_requests'), 10, 3);

        foreach (self::PROTECTED_AJAX_ACTIONS as $ajax_action) {
            add_action('wp_ajax_' . $ajax_action, array($this, 'protect_ajax_request'), 1);
            add_action('wp_ajax_nopriv_' . $ajax_action, array($this, 'protect_ajax_request'), 1);
        }
        add_filter('adverts_form_load', array($this, 'make_frontend_contact_fields_read_only'), 20);
        add_filter('adverts_add_form_bind', array($this, 'bind_profile_contact_defaults'), 20);
        add_action('adverts_form_bind', array($this, 'enforce_profile_contact_values'), 20, 2);
        add_action('adverts_post_save', array($this, 'sync_saved_advert_contact'), 20, 2);

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
     * Deny WPAdverts admin-ajax actions to unauthorized visitors.
     *
     * Registered at priority 1 so it runs before the WPAdverts handlers.
     *
     * @return void
     */
    public function protect_ajax_request() {
        if ($this->current_user_can_access()) {
            return;
        }

        wp_send_json_error(
            array('message' => __('You must be an authorized member to access classifieds.', 'wpadverts-armember')),
            401
        );
    }

    /**
     * Remove adverts from frontend search results for unauthorized visitors.
     *
     * Adverts are a public post type, so a plain /?s=term query would expose
     * advert titles and excerpts without ever hitting a protected surface.
     *
     * @param WP_Query $query Query being prepared.
     * @return void
     */
    public function exclude_adverts_from_search($query) {
        if (!($query instanceof WP_Query) || is_admin()) {
            return;
        }

        // REST search with no term leaves is_search() false, but the search
        // handler still puts the requested subtypes into post_type.
        $is_rest_advert_query = defined('REST_REQUEST') && REST_REQUEST
            && in_array('advert', (array) $query->get('post_type'), true);

        if (!$query->is_search() && !$is_rest_advert_query) {
            return;
        }

        if ($this->current_user_can_access()) {
            return;
        }

        $post_types = $query->get('post_type');

        if (empty($post_types) || 'any' === $post_types) {
            $post_types = get_post_types(array('exclude_from_search' => false));
        }

        $post_types = array_diff((array) $post_types, array('advert'));

        if (empty($post_types)) {
            $query->set('post__in', array(0));
            return;
        }

        $query->set('post_type', array_values($post_types));
    }

    /**
     * Blank WPAdverts blocks rendered outside page content.
     *
     * Block themes can place WPAdverts blocks in templates, template parts,
     * synced patterns, and widget areas, none of which appear in post_content
     * and therefore none of which the template_redirect check can detect.
     *
     * @param string $content    Rendered block markup.
     * @param array  $parsed_block Parsed block data.
     * @return string
     */
    public function protect_rendered_blocks($content, $parsed_block) {
        if ($this->is_wp_admin_request() || !is_array($parsed_block) || empty($parsed_block['blockName'])) {
            return $content;
        }

        if (0 !== strpos($parsed_block['blockName'], 'wpadverts/')) {
            return $content;
        }

        if ($this->current_user_can_access()) {
            return $content;
        }

        return $this->blocked_content_notice();
    }

    /**
     * Blank WPAdverts shortcodes rendered outside page content.
     *
     * Template parts, synced patterns, widgets, and other plugins can render
     * WPAdverts shortcodes without them appearing in post_content.
     *
     * @param string $output Rendered shortcode markup.
     * @param string $tag    Shortcode name.
     * @return string
     */
    public function protect_rendered_shortcodes($output, $tag) {
        if ($this->is_wp_admin_request() || !in_array($tag, self::PROTECTED_SHORTCODES, true)) {
            return $output;
        }

        if ($this->current_user_can_access()) {
            return $output;
        }

        return $this->blocked_content_notice();
    }

    /**
     * Whether the request targets wp-admin proper, excluding admin-ajax.php.
     *
     * @return bool
     */
    private function is_wp_admin_request() {
        return is_admin() && !wp_doing_ajax();
    }

    /**
     * Replacement markup shown where protected content was removed.
     *
     * @return string
     */
    private function blocked_content_notice() {
        return sprintf(
            '<div class="wpaag-blocked-content"><p>%s</p></div>',
            esc_html__('You must be an authorized member to view classifieds.', 'wpadverts-armember')
        );
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
        if (!$this->is_protected_rest_route($route, $request)) {
            return $result;
        }

        return new WP_Error(
            'wpaag_access_denied',
            __('You must be an authorized member to access classifieds.', 'wpadverts-armember'),
            array('status' => 401)
        );
    }

    /**
     * Make contact name and email read-only on frontend advert forms.
     *
     * The values remain visible for reference and in the form scheme because
     * WPAdverts contact, notification, and payment features depend on their
     * metadata. WordPress administration forms remain unchanged.
     *
     * @param mixed $form WPAdverts form scheme.
     * @return mixed
     */
    public function make_frontend_contact_fields_read_only($form) {
        if (is_admin() || !is_user_logged_in() || !is_array($form)) {
            return $form;
        }

        if (!isset($form['name']) || 'advert' !== $form['name'] || empty($form['field']) || !is_array($form['field'])) {
            return $form;
        }

        foreach ($form['field'] as &$field) {
            if (
                !is_array($field)
                || empty($field['name'])
                || !in_array($field['name'], array('adverts_person', 'adverts_email'), true)
            ) {
                continue;
            }

            $field['type']        = 'adverts_field_text';
            $field['description'] = __('From your membership profile. Update your profile to change this value.', 'wpadverts-armember');
            $field['attr']        = isset($field['attr']) && is_array($field['attr']) ? $field['attr'] : array();
            $field['attr']['readonly']      = 'readonly';
            $field['attr']['aria-readonly'] = 'true';
            $field['class'] = trim(
                (isset($field['class']) ? $field['class'] : '') . ' wpaag-profile-contact'
            );
        }
        unset($field);

        return $form;
    }

    /**
     * Supply profile contact values when a new advert form is initialized.
     *
     * @param mixed $bind Form values.
     * @return mixed
     */
    public function bind_profile_contact_defaults($bind) {
        if (!is_user_logged_in() || !is_array($bind)) {
            return $bind;
        }

        $post_id = isset($bind['_post_id']) ? absint($bind['_post_id']) : 0;
        $contact = $this->get_advert_contact($post_id);

        if ($contact) {
            $bind['adverts_person'] = $contact['name'];
            $bind['adverts_email']  = $contact['email'];
        }

        return $bind;
    }

    /**
     * Replace submitted contact values before WPAdverts validation and saving.
     *
     * @param mixed $form WPAdverts form object.
     * @param mixed $data Submitted values.
     * @return void
     */
    public function enforce_profile_contact_values($form, $data) {
        if (
            is_admin()
            || !is_user_logged_in()
            || !is_object($form)
            || !method_exists($form, 'set_value')
        ) {
            return;
        }

        $data    = is_array($data) ? $data : array();
        $post_id = isset($data['_post_id']) ? absint($data['_post_id']) : 0;
        $contact = $this->get_advert_contact($post_id);

        if (!$contact) {
            return;
        }

        $form->set_value('adverts_person', $contact['name']);
        $form->set_value('adverts_email', $contact['email']);
    }

    /**
     * Keep saved advert metadata synchronized with the advert owner's profile.
     *
     * @param mixed $form    WPAdverts form object.
     * @param int   $post_id Saved advert ID.
     * @return void
     */
    public function sync_saved_advert_contact($form, $post_id) {
        unset($form);

        $post_id = absint($post_id);
        if (!$post_id || 'advert' !== get_post_type($post_id)) {
            return;
        }

        $contact = $this->get_advert_contact($post_id);
        if (!$contact) {
            return;
        }

        update_post_meta($post_id, 'adverts_person', $contact['name']);
        update_post_meta($post_id, 'adverts_email', $contact['email']);
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

        // Access is evaluated on every block render and search query, so the
        // per-user decision is memoized for the duration of the request.
        if (isset($this->access_cache[$user_id])) {
            return $this->access_cache[$user_id];
        }

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
        $this->access_cache[$user_id] = (bool) apply_filters('wpaag_user_can_access', $allowed, $user_id);

        return $this->access_cache[$user_id];
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

        foreach (self::PROTECTED_BLOCKS as $block_name) {
            if (has_block($block_name, $post)) {
                return true;
            }
        }

        foreach (self::PROTECTED_SHORTCODES as $shortcode) {
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
     * Route prefixes cover the dedicated advert endpoints. Core also exposes
     * advert data through generic routes, so those are resolved by post type
     * rather than by route string.
     *
     * @param string          $route   REST route.
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    private function is_protected_rest_route($route, $request) {
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

        if (0 === strpos($route, '/oembed/')) {
            $url     = (string) $request->get_param('url');
            $post_id = $url ? url_to_postid($url) : 0;

            return $post_id && 'advert' === get_post_type($post_id);
        }

        if (0 === strpos($route, '/wp/v2/search')) {
            // Requests that do not name adverts explicitly stay available; the
            // pre_get_posts filter strips adverts from their results instead,
            // so site-wide REST search keeps working for anonymous visitors.
            $subtypes = array_filter((array) $request->get_param('subtype'));

            return in_array('advert', $subtypes, true);
        }

        foreach (array('/wp/v2/media', '/wp/v2/comments') as $prefix) {
            if (0 !== strpos($route, $prefix)) {
                continue;
            }

            $parents = $request->get_param('parent');
            if (null === $parents || array() === $parents) {
                $parents = $request->get_param('post');
            }

            foreach ((array) $parents as $parent_id) {
                if ('advert' === get_post_type(absint($parent_id))) {
                    return true;
                }
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

        $destination_url = add_query_arg('redirect_to', rawurlencode($target), $destination_url);
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
        $input       = is_array($input) ? $input : array();
        $mode        = isset($input['access_mode']) ? sanitize_key($input['access_mode']) : 'recognized_user';
        $name_source = isset($input['contact_name_source']) ? sanitize_key($input['contact_name_source']) : 'display_name';

        if (!in_array($mode, array('recognized_user', 'valid_plan'), true)) {
            $mode = 'recognized_user';
        }

        if (!in_array($name_source, array('display_name', 'first_last'), true)) {
            $name_source = 'display_name';
        }

        return array(
            'access_mode'         => $mode,
            'destination_page_id' => isset($input['destination_page_id']) ? absint($input['destination_page_id']) : 0,
            'contact_name_source' => $name_source,
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

                        <div class="wpaag-field">
                            <div>
                                <label for="wpaag-contact-name-source"><?php esc_html_e('Advert contact name', 'wpadverts-armember'); ?></label>
                                <p><?php esc_html_e('Contact Person and Email are shown read-only on frontend advert forms and synchronized from the advert owner’s ARMember/WordPress profile.', 'wpadverts-armember'); ?></p>
                            </div>
                            <select id="wpaag-contact-name-source" name="<?php echo esc_attr(self::OPTION_NAME); ?>[contact_name_source]">
                                <option value="display_name" <?php selected($settings['contact_name_source'], 'display_name'); ?>>
                                    <?php esc_html_e('ARMember/WordPress display name', 'wpadverts-armember'); ?>
                                </option>
                                <option value="first_last" <?php selected($settings['contact_name_source'], 'first_last'); ?>>
                                    <?php esc_html_e('First and last name', 'wpadverts-armember'); ?>
                                </option>
                            </select>
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
            'contact_name_source' => 'display_name',
        );
    }

    /**
     * Resolve the profile contact values for a new or existing advert.
     *
     * Existing adverts use their post author so an administrator editing an
     * advert cannot accidentally replace the owner's contact information.
     *
     * @param int $post_id Existing advert ID, or zero for a new advert.
     * @return array{name:string,email:string}|null
     */
    private function get_advert_contact($post_id = 0) {
        $user_id = get_current_user_id();

        if ($post_id) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post && 'advert' === $post->post_type && $post->post_author) {
                $user_id = (int) $post->post_author;
            }
        }

        if (!$user_id) {
            return null;
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User) || !$user->user_email) {
            return null;
        }

        $settings = $this->get_settings();
        $name     = $user->display_name;

        if ('first_last' === $settings['contact_name_source']) {
            $first_name = trim((string) get_user_meta($user_id, 'first_name', true));
            $last_name  = trim((string) get_user_meta($user_id, 'last_name', true));
            $full_name  = trim($first_name . ' ' . $last_name);

            if ('' !== $full_name) {
                $name = $full_name;
            }
        }

        /**
         * Filter profile-derived contact information before it is saved.
         *
         * @param array{name:string,email:string} $contact Profile contact data.
         * @param int                             $user_id WordPress user ID.
         * @param int                             $post_id Advert ID, or zero.
         */
        $contact = apply_filters(
            'wpaag_advert_contact',
            array(
                'name'  => sanitize_text_field($name),
                'email' => sanitize_email($user->user_email),
            ),
            $user_id,
            $post_id
        );

        if (!is_array($contact) || empty($contact['name']) || !is_email($contact['email'])) {
            return null;
        }

        return array(
            'name'  => sanitize_text_field($contact['name']),
            'email' => sanitize_email($contact['email']),
        );
    }

    private function __construct() {
    }
}
