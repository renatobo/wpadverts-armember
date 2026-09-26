<?php
/**
 * Access-control tests for WPAAG_Plugin.
 *
 * @package WPAdverts_ARMember
 */

use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {
    private WPAAG_Plugin $plugin;

    protected function setUp(): void {
        WPAAG_Test_State::reset();

        $instance = new ReflectionProperty(WPAAG_Plugin::class, 'instance');
        if (PHP_VERSION_ID < 80100) {
            $instance->setAccessible(true);
        }
        $instance->setValue(null, null);

        $this->plugin = WPAAG_Plugin::instance();
    }

    private function call(string $method, ...$args) {
        $reflection = new ReflectionMethod(WPAAG_Plugin::class, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invoke($this->plugin, ...$args);
    }

    private function useAccessMode(string $mode): void {
        WPAAG_Test_State::$options['wpaag_settings'] = array('access_mode' => $mode);
    }

    public function test_anonymous_visitor_is_denied(): void {
        $this->assertFalse($this->plugin->current_user_can_access());
    }

    public function test_access_filter_can_grant_anonymous_visitors(): void {
        WPAAG_Test_State::$filters['wpaag_user_can_access'] = fn($allowed, $user_id) => 0 === $user_id;

        $this->assertTrue($this->plugin->current_user_can_access());
    }

    public function test_administrator_is_allowed_without_armember_status(): void {
        WPAAG_Test_State::$user_id = 1;
        WPAAG_Test_State::$admins  = array(1);

        $this->assertTrue($this->plugin->current_user_can_access());
    }

    public function test_user_unknown_to_armember_is_denied(): void {
        WPAAG_Test_State::$user_id = 2;

        $this->assertFalse($this->plugin->current_user_can_access());
    }

    public function test_default_mode_allows_active_member(): void {
        WPAAG_Test_State::$user_id       = 3;
        WPAAG_Test_State::$arm_status[3] = '1';

        $this->assertTrue($this->plugin->current_user_can_access());
    }

    /**
     * @dataProvider inactive_statuses
     */
    public function test_default_mode_denies_inactive_member(string $status): void {
        WPAAG_Test_State::$user_id       = 4;
        WPAAG_Test_State::$arm_status[4] = $status;

        $this->assertFalse($this->plugin->current_user_can_access());
    }

    public static function inactive_statuses(): array {
        return array(
            'inactive'   => array('2'),
            'pending'    => array('3'),
            'terminated' => array('4'),
        );
    }

    public function test_valid_plan_mode_denies_active_member_without_plan(): void {
        $this->useAccessMode('valid_plan');
        WPAAG_Test_State::$user_id       = 5;
        WPAAG_Test_State::$arm_status[5] = '1';

        $this->assertFalse($this->plugin->current_user_can_access());
    }

    public function test_valid_plan_mode_allows_member_with_plan(): void {
        $this->useAccessMode('valid_plan');
        WPAAG_Test_State::$user_id       = 6;
        WPAAG_Test_State::$arm_status[6] = '1';
        WPAAG_Test_State::$user_meta[6]  = array('arm_user_plan_ids' => array(10));

        $this->assertTrue($this->plugin->current_user_can_access());
    }

    public function test_valid_plan_mode_denies_member_whose_only_plan_is_suspended(): void {
        $this->useAccessMode('valid_plan');
        WPAAG_Test_State::$user_id       = 7;
        WPAAG_Test_State::$arm_status[7] = '1';
        WPAAG_Test_State::$user_meta[7]  = array(
            'arm_user_plan_ids'           => array(10),
            'arm_user_suspended_plan_ids' => array(10),
        );

        $this->assertFalse($this->plugin->current_user_can_access());
    }

    /**
     * @dataProvider protected_routes
     */
    public function test_protected_rest_routes_return_401(string $route): void {
        $result = $this->plugin->protect_rest_requests(null, null, new WP_REST_Request($route));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public static function protected_routes(): array {
        return array(
            'advert collection'       => array('/wp/v2/advert'),
            'uppercase advert route'  => array('/wp/v2/ADVERT'),
            'mixed-case advert item'  => array('/wp/v2/Advert/12'),
            'advert category'         => array('/wp/v2/advert_category'),
            'uppercase wpadverts'     => array('/WPADVERTS/v1/anything'),
        );
    }

    public function test_unrelated_rest_route_is_not_blocked(): void {
        $this->assertNull($this->plugin->protect_rest_requests(null, null, new WP_REST_Request('/wp/v2/posts')));
    }

    public function test_single_media_item_attached_to_advert_is_blocked(): void {
        WPAAG_Test_State::$posts[12] = new WP_Post(array('ID' => 12, 'post_type' => 'advert'));
        WPAAG_Test_State::$posts[30] = new WP_Post(array('ID' => 30, 'post_type' => 'attachment', 'post_parent' => 12));
        WPAAG_Test_State::$posts[31] = new WP_Post(array('ID' => 31, 'post_type' => 'attachment'));

        $this->assertInstanceOf(WP_Error::class, $this->plugin->protect_rest_requests(null, null, new WP_REST_Request('/wp/v2/media/30')));
        $this->assertNull($this->plugin->protect_rest_requests(null, null, new WP_REST_Request('/wp/v2/media/31')));
    }

    public function test_single_comment_on_advert_is_blocked(): void {
        WPAAG_Test_State::$posts[12]    = new WP_Post(array('ID' => 12, 'post_type' => 'advert'));
        WPAAG_Test_State::$comments[40] = (object) array('comment_post_ID' => 12);

        $this->assertInstanceOf(WP_Error::class, $this->plugin->protect_rest_requests(null, null, new WP_REST_Request('/wp/v2/comments/40')));
    }

    public function test_media_collection_is_flagged_for_unauthorized_visitors(): void {
        $args = $this->plugin->exclude_advert_attachments_from_rest(array());

        $this->assertTrue($args['wpaag_exclude_advert_children']);
    }

    public function test_array_post_type_query_is_protected(): void {
        WPAAG_Test_State::$query_vars['post_type'] = array('post', 'advert');

        $this->assertTrue($this->call('is_protected_frontend_request'));
    }

    public function test_attachment_of_advert_is_protected(): void {
        WPAAG_Test_State::$posts[12]                    = new WP_Post(array('ID' => 12, 'post_type' => 'advert'));
        WPAAG_Test_State::$conditionals                 = array('attachment');
        WPAAG_Test_State::$query_vars['queried_object'] = new WP_Post(array('ID' => 30, 'post_type' => 'attachment', 'post_parent' => 12));

        $this->assertTrue($this->call('is_protected_frontend_request'));
    }

    public function test_sitemaps_drop_adverts_for_unauthorized_visitors(): void {
        $post_types = $this->plugin->exclude_adverts_from_sitemaps(array('post' => 1, 'advert' => 1));
        $taxonomies = $this->plugin->exclude_advert_categories_from_sitemaps(array('category' => 1, 'advert_category' => 1));

        $this->assertSame(array('post' => 1), $post_types);
        $this->assertSame(array('category' => 1), $taxonomies);
    }

    public function test_current_url_on_subdirectory_install_is_not_doubled(): void {
        WPAAG_Test_State::$home_url = 'https://example.test/sub';
        $_SERVER['REQUEST_URI']     = '/sub/advert/bike/?x=1';

        $this->assertSame('https://example.test/sub/advert/bike/?x=1', $this->call('get_current_url'));
    }

    public function test_current_url_on_root_install(): void {
        $_SERVER['REQUEST_URI'] = '/advert/bike/';

        $this->assertSame('https://example.test/advert/bike/', $this->call('get_current_url'));
    }

    public function test_logged_out_visitor_uses_unauthorized_destination(): void {
        WPAAG_Test_State::$options['wpaag_settings'] = array(
            'destination_page_id'        => 5,
            'member_destination_page_id' => 9,
        );

        $this->assertSame(5, $this->call('get_destination_page_id'));
    }

    public function test_logged_in_user_uses_member_destination(): void {
        WPAAG_Test_State::$options['wpaag_settings'] = array(
            'destination_page_id'        => 5,
            'member_destination_page_id' => 9,
        );
        WPAAG_Test_State::$user_id = 8;

        $this->assertSame(9, $this->call('get_destination_page_id'));
    }

    public function test_logged_in_user_falls_back_to_unauthorized_destination(): void {
        WPAAG_Test_State::$options['wpaag_settings'] = array('destination_page_id' => 5);
        WPAAG_Test_State::$user_id = 8;

        $this->assertSame(5, $this->call('get_destination_page_id'));
    }

    public function test_submitted_advert_id_of_another_owner_is_rejected(): void {
        WPAAG_Test_State::$user_id   = 3;
        WPAAG_Test_State::$posts[12] = new WP_Post(array('ID' => 12, 'post_type' => 'advert', 'post_author' => 99));
        WPAAG_Test_State::$posts[13] = new WP_Post(array('ID' => 13, 'post_type' => 'advert', 'post_author' => 3));

        $this->assertSame(0, $this->call('resolve_request_advert_id', '12'));
        $this->assertSame(13, $this->call('resolve_request_advert_id', '13'));

        WPAAG_Test_State::$edit_caps = array(12);
        $this->assertSame(12, $this->call('resolve_request_advert_id', 12));
    }
}
