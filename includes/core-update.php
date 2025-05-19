<?php
if (!defined('ABSPATH'))
    exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/update-core.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

if (!class_exists('Silent_Upgrader_Skin')) {
    class Silent_Upgrader_Skin extends WP_Upgrader_Skin
    {
        public function feedback($string, ...$args)
        {
        }
    }
}

class DBBS_Core_Update
{
    private $new_access_token = null;
    private $headers = [];


    private $auth;
    public function __construct()
    {
        $this->auth = DBBS_JWT_Auth::get_instance();
    }

    public function register_api()
    {
        register_rest_route('dbbs/v1', '/update-core', [
            'methods' => 'POST',
            'callback' => [$this, 'update_core'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

public function check_permission(WP_REST_Request $request)
{
    $result = $this->auth->check_permission($request);

    if ($this->auth->get_new_access_token()) {
        $this->new_access_token = $this->auth->get_new_access_token();
    }

    if ($this->new_access_token) {
        $this->headers['x-access-token'] = $this->new_access_token; 
    }

    return $result;
}



    public function update_core()
    {
        if (get_transient('dbbs_core_updating')) {

            return new WP_REST_Response(['message' => 'در حال حاضر یک عملیات آپدیت در حال انجام است'], 400,$this->headers);
        }
        set_transient('dbbs_core_updating', true, 600);

        set_time_limit(300);

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            WP_Filesystem();
        }

        wp_version_check();
        $updates = get_core_updates();

        if (empty($updates) || $updates[0]->response !== 'upgrade') {
            delete_transient('dbbs_core_updating');


            return new WP_REST_Response(['message' => 'آپدیت جدیدی در حال حاضر وجود ندارد'], 200,$this->headers);
        }


        try {
            $this->enable_maintenance_mode();

            $upgrader = new Core_Upgrader(new Silent_Upgrader_Skin());
            $result = $upgrader->upgrade($updates[0]);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            $this->disable_maintenance_mode();
            delete_transient('dbbs_core_updating');


            $body = [
                'message' => 'آپدیت به طور کامل انجام شد',
                'new_version' => get_bloginfo('version'),
            ];


            return new WP_REST_Response($body, 200, $this->headers);


        } catch (Exception $e) {
            $this->disable_maintenance_mode();
            delete_transient('dbbs_core_updating');

            return new WP_REST_Response([
                'error' => 'خطا در آپدیت: ' . $e->getMessage(),
            ], 500,$this->headers);
        }
    }


    private function disable_maintenance_mode()
    {

        $file = ABSPATH . '.maintenance';

        if (file_exists($file)) {
            unlink($file);
        }
    }


    private function enable_maintenance_mode()
    {
        $content = '<?php $upgrading = ' . time() . '; ?>';
        file_put_contents(ABSPATH . '.maintenance', $content);
    }

}