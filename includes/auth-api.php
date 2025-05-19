<?php
if (!defined('ABSPATH')) exit;

class DBBS_Auth_API {
    private $auth;

    public function __construct() {
        $this->auth = DBBS_JWT_Auth::get_instance();
    }

    public function register_api() {
        register_rest_route('dbbs/v1', '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_login'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function handle_login(WP_REST_Request $request) {

        $username = $request->get_param('username');
        $password = $request->get_param('password');

        if (!$username || !$password) {
            return new WP_Error('missing_credentials', 'نام کاربری یا رمز عبور وارد نشده است.', ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_login', 'نام کاربری یا رمز عبور اشتباه است.', ['status' => 401]);
        }

        $access_token = $this->auth->generate_access_token($user->ID);
        $refresh_token = $this->auth->generate_refresh_token($user->ID);

        return new WP_REST_Response([
            'message' => 'ورود با موفقیت انجام شد',
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ], 200);
        
    }

}
