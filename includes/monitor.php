<?php
if (!defined('ABSPATH'))
    exit;

class DBBS_Monitor
{

    private $auth;

    public function __construct()
    {
        $this->auth = DBBS_JWT_Auth::get_instance();
    }

    public function register_api()
    {
        register_rest_route('dbbs/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_health'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(WP_REST_Request $request)
    {
        return $this->auth->check_permission($request);
    }


    public function get_site_health()
    {
        $health_data = [];

        // اطلاعات وردپرس
        $health_data['wordpress'] = [
            'version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
        ];

        // اطلاعات سرور
        $health_data['server'] = [
            'php_version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        // اطلاعات دیتابیس
        global $wpdb;
        $health_data['database'] = [
            'name' => $wpdb->dbname ?? 'Unknown',
            'size' => $this->get_db_size(),
            'tables' => $this->get_db_tables_count(),
        ];

        // اطلاعات افزونه‌ها
        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $health_data['plugins'] = [];
        foreach ($plugins as $plugin_path => $plugin_data) {
            $health_data['plugins'][] = [
                'name' => $plugin_data['Name'] ?? 'Unknown',
                'version' => $plugin_data['Version'] ?? 'Unknown',
                'status' => in_array($plugin_path, $active_plugins) ? 'Active' : 'Inactive',
            ];
        }

        // اطلاعات امنیتی
        $health_data['security'] = [
            'https_enabled' => is_ssl(),
            'ssl_certificate_valid' => $this->check_ssl_validity(),
        ];

        // اطلاعات عملکرد
        $health_data['performance'] = [
            'caching_enabled' => wp_using_ext_object_cache() || (defined('WP_CACHE') && WP_CACHE),
        ];



        $new_access_token = $this->auth->get_new_access_token();
        // اگر توکن جدید ساخته شده باشد، در هدر ارسال می‌شود
        $headers = [];
        if ($new_access_token) {
            $headers['x-access-token'] = $new_access_token;
        }

        return new WP_REST_Response($health_data, 200, $headers);

    }

    private function get_db_size()
    {
        global $wpdb;
        $results = $wpdb->get_results('SHOW TABLE STATUS');
        $db_size = 0;
        foreach ($results as $row) {
            $db_size += ($row->Data_length ?? 0) + ($row->Index_length ?? 0);
        }
        return size_format($db_size);
    }

    private function get_db_tables_count()
    {
        global $wpdb;
        $result = $wpdb->get_results("SHOW TABLES");
        return count($result);
    }

    private function check_ssl_validity()
    {
        $url = get_site_url();
        $response = wp_remote_get(
            $url,
            [
                'timeout' => 5,
                'sslverify' => true
            ]
        );
        if (is_wp_error($response)) {
            return false;
        }
        return true;
    }
}