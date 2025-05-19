<?php
if (!defined('ABSPATH')) exit;


/*
Plugin Name: WP Site Pilot
Description: بکاپ، آپدیت هسته و مانیتورینگ سایت‌های وردپرسی با درخواست های رست .
Version: 1.2.1
Author: Milad Motavakel
*/

define('WP_SITE_PILOT_PATH', plugin_dir_path(__FILE__));

require_once WP_SITE_PILOT_PATH . 'includes/backup.php';
require_once WP_SITE_PILOT_PATH . 'includes/core-update.php';
require_once WP_SITE_PILOT_PATH . 'includes/monitor.php';
require_once WP_SITE_PILOT_PATH . 'includes/auth-api.php';

require_once WP_SITE_PILOT_PATH . 'includes/utils/jwt-auth.php';
require_once WP_SITE_PILOT_PATH . 'vendor/autoload.php';



class DBBS_Main {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_apis']);
    }

    public function register_apis() {
        
        (new DBBS_Backup())->register_api();
        (new DBBS_Core_Update())->register_api();
        (new DBBS_Monitor())->register_api();
        (new DBBS_Auth_API())->register_api();
    }
}

new DBBS_Main();