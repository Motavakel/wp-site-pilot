<?php
if (!defined('ABSPATH')) exit;


class DBBS_Backup {

    private $auth;
    public function __construct() {
        $this->auth = DBBS_JWT_Auth::get_instance();
    }
    
    public function register_api() {
        register_rest_route('dbbs/v1', '/backup', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_backup'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(WP_REST_Request $request) {
        return $this->auth->check_permission($request);
    }

    public function generate_backup() {
        global $wpdb;

        $backup = "-- DB Backup - " . date('Y-m-d H:i:s') . "\n\n";
        $tables = $wpdb->get_col('SHOW TABLES');

        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            $backup .= $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
            foreach ($rows as $row) {
                $values = array_map(function ($v) {
                    return is_null($v) ? 'NULL' : "'" . esc_sql($v) . "'";
                }, $row);
                $backup .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $backup .= "\n";
        }


            $new_access_token = $this->auth->get_new_access_token();
            
            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="db-backup-' . date('Ymd_His') . '.sql"',
            ];
            if ($new_access_token) {
                $headers['x-access-token'] = $new_access_token;
            }


            return new WP_REST_Response($backup, 200, $headers);

    }
}