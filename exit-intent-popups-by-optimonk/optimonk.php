<?php
/*
  Plugin Name: OptiMonk: Popups, Personalization & A/B Testing
  Plugin URI: https://www.optimonk.com/
  Description: OptiMonk, the conversion optimization toolset crafted for marketers
  Author: OptiMonk
  Version: 2.1.1
  Text Domain: optimonk
  Domain Path: /languages
  Author URI: http://www.optimonk.com/
  License: GPLv2
*/

define('OM_PLUGIN_VERSION', '2.1.1');
define('OPTIMONK_FRONT_DOMAIN', 'onsite.optimonk.com');

if (!defined('ABSPATH')) {
    die('');
}
require_once 'wc-attributes.php';
require_once(dirname(__FILE__) . "/optimonk-admin.php");
require_once(dirname(__FILE__) . "/optimonk-front.php");
require_once(dirname(__FILE__) . "/include/class-notification.php");

if (class_exists('OptiMonkAdmin') && class_exists('OptiMonkFront')) {
    $protocol = 'http://';
    $https = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'off';
    $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    $protocol = $https !== 'off' || $port == 443 ? 'https://' : 'http://';

    $currentUrl = $protocol . $host . $requestUri;

    if (!is_admin() && strpos($currentUrl, wp_login_url()) !== 0) {
        add_action('wp_head', array('OptiMonkFront', 'init'), 1);
        add_action('wp_enqueue_scripts', array('OptiMonkAdmin', 'initStyleSheet'));
    }

    register_activation_hook(__FILE__, array('OptiMonkAdmin', 'activate'));
    register_uninstall_hook(__FILE__, array('OptiMonkAdmin', 'uninstall'));
    add_action('admin_init', array('OptiMonkAdmin', 'redirectToSettingPage'));
    add_action('admin_init', array('OptiMonkAdmin', 'initFeedbackNotification'));

    $optiMonkAdmin = new OptiMonkAdmin(__FILE__);
    $optiMonkFront = new OptiMonkFront();
}
