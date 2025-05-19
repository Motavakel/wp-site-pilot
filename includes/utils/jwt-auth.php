<?php
if (!defined('ABSPATH'))
    exit;


use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DBBS_JWT_Auth
{


    private static $instance = null;
    private $secret_key;
    private $algorithm = 'HS256';
    private $refresh_expiration = 7 * DAY_IN_SECONDS;
    private $access_expiration = 8 * HOUR_IN_SECONDS ;



    private $new_access_token = null;
    public function get_new_access_token() {
        return $this->new_access_token;
    }

    private function __construct()
    {
        $config = include WP_SITE_PILOT_PATH . 'config.php';
        $this->secret_key = $config['jwt_secret'];
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generate_access_token($user_id)
    {
        $created_at = time();
        $expiration = $created_at + $this->access_expiration;

        $payload = [
            'iat' => $created_at,
            'exp' => $expiration,
            'user_id' => $user_id,
            'type' => 'access'
        ];

        return JWT::encode($payload, $this->secret_key, $this->algorithm);
    }

    public function generate_refresh_token($user_id)
    {
        $created_at = time();
        $expiration = $created_at + $this->refresh_expiration;

        $payload = [
            'iat' => $created_at,
            'exp' => $expiration,
            'user_id' => $user_id,
            'type' => 'refresh'
        ];

        return JWT::encode($payload, $this->secret_key, $this->algorithm);
    }


    public function validate_token($token, $expected_type = 'access')
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, $this->algorithm));
            $decoded_array = (array) $decoded;

            if (!isset($decoded_array['type']) || $decoded_array['type'] !== $expected_type) {
                return new WP_Error('invalid_token_type', 'نوع توکن معتبر نیست', ['status' => 403]);
            }

            return $decoded_array;
        } catch (Exception $e) {
            return new WP_Error('invalid_token', 'توکن معتبر نیست یا منقضی شده است', ['status' => 403]);
        }
    }

    public function check_permission(WP_REST_Request $request)
    {

        $auth = $request->get_header('authorization');
        if (!$auth || !preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            return new WP_Error('missing_token', 'توکن ارسال نشده یا نادرست است', ['status' => 401]);
        }

        $access_token = $matches[1];
        $validation = $this->validate_token($access_token);
        if (is_wp_error($validation)) {

            $refresh_token = $request->get_header('x-refresh-token');
            if (!$refresh_token) {
                return new WP_Error('access_denied', 'دسترسی منقضی شده و توکن رفرش وجود ندارد', ['status' => 401]);
            }

            $refresh_validation = $this->validate_token($refresh_token, 'refresh');
            if (is_wp_error($refresh_validation)) {
                return $refresh_validation;
            }
            $new_access = $this->generate_access_token($refresh_validation['user_id']);
            $this->new_access_token = $new_access;
        }
        return true;
    }

}
