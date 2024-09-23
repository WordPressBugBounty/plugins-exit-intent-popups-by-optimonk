<?php

/**
 * @Class OptiMonkAdmin
 */
class OptiMonkAdmin
{
    /**
     * @var string
     */
    protected static $pluginLink = 'themes.php?page=optimonk';
    /**
     * @var
     */
    protected static $basePath;

    /**
     * @param $pluginBasePath
     */
    public function __construct($pluginBasePath)
    {
        self::$basePath = $pluginBasePath;
        add_filter('plugin_action_links_' . plugin_basename(self::$basePath), array($this, 'addSettingsPageLink'));
        add_action('admin_enqueue_scripts', array($this, 'initScripts'));
        add_action('admin_enqueue_scripts', array($this, 'initStyleSheet'));
        add_action('admin_init', array($this, 'initSettings'));
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_optimonk_settings', array($this, 'postHandler'));
        add_action('plugins_loaded', array($this, 'loadTextDomain'));
        add_action('admin_footer', array($this, 'settings_javascript'));
        add_action('wp_ajax_setting_form', array($this, 'postHandler'));
    }

    public static function initFeedbackNotification()
    {
        $notification = new OptiMonk_Notification();
        $notification->initialize();
    }

    public function loadTextDomain()
    {
        load_plugin_textdomain('optimonk', FALSE, basename(dirname(__FILE__)) . '/languages/');
    }

    public static function activate()
    {
        OptiMonkAdmin::install();
        add_option('optiMonkDoActivationRedirect', true);
    }

    public static function install()
    {
        try {
            OptiMonkAdmin::sendAction('install');
        } catch (Exception $e) {
            error_log('Error during plugin activation tracking: ' . $e->getMessage());
        }
    }

    public static function uninstall()
    {
        try {
            OptiMonkAdmin::sendAction('uninstall');
        } catch (Exception $e) {
            error_log('Error during plugin uninstallation tracking: ' . $e->getMessage());
        }
    }

    private static function sendAction($type)
    {
        try {
            $data = array(
                'type' => $type,
                'domain' => get_site_url(),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            );
            $args = array(
                'body' => wp_json_encode($data),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 1,
                'blocking' => false
            );

            wp_remote_post("https://backend.optimonk.com/app/wordpress/track?token=yNGfPlqxPQD97FbZ6URy01d0n", $args);
        } catch (Exception $e) {
            error_log('Error sending tracking data: ' . $e->getMessage());
        }
    }

    /**
     * @param $links
     *
     * @return mixed
     */
    public function addSettingsPageLink($links)
    {
        $settings_link = '<a href="' . self::$pluginLink . '">' . __('Settings', 'optimonk') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function redirectToSettingPage()
    {
        if (get_option('optiMonkDoActivationRedirect', false)) {
            delete_option('optiMonkDoActivationRedirect');
            wp_redirect(self::$pluginLink);
        }
    }

    public function initSettings()
    {
        register_setting('optiMonk', 'accountId', 'intval');
    }

    public function initScripts()
    {
        try {
            $WPVersionStr = get_bloginfo('version');
            $WPVersionExp = explode('.', $WPVersionStr);

            if (
                $WPVersionExp[0] &&
                intval($WPVersionExp[0]) <= 5 &&
                $WPVersionExp[1] &&
                intval($WPVersionExp[1]) < 6
            ) {
                wp_enqueue_script('jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.js');
            }
        } catch (Exception $e) {
            wp_enqueue_script('jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.js');
        }
    }

    public static function initStyleSheet()
    {
        if (is_admin()) {
            wp_enqueue_style('optimonk-style', plugin_dir_url(__FILE__) . 'css/optimonk-style.css');
        }
    }

    public function menu()
    {
        $notification = new OptiMonk_Notification();
        if ($notification->isEnabled()) {
            add_submenu_page(
                'themes.php',
                __('OptiMonk', 'optimonk'),
                __('OptiMonk', 'optimonk') . ' <span id="om-notification-bubble" class="update-plugins count-1 om-count"><span class="plugin-count">1</span></span>',
                'edit_theme_options',
                'optimonk',
                array($this, 'settings')
            );
        } else {
            add_submenu_page(
                'themes.php',
                __('OptiMonk', 'optimonk'),
                __('OptiMonk', 'optimonk'),
                'edit_theme_options',
                'optimonk',
                array($this, 'settings')
            );
        }
    }

    public function settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'optimonk'));
        }

        $version = get_plugin_data(self::$basePath)['Version'];
        $reviewUrl = 'https://wordpress.org/support/view/plugin-reviews/exit-intent-popups-by-optimonk#postform';
        $reviewLinkText = __('Review the plugin', 'optimonk');
        $reviewLink = '<a class="optimonk-link" target="_blank" href="' . $reviewUrl . '">' . $reviewLinkText . '</a>';
        $registerUrl = $this->getSalesDomain() .
            '/register?utm_source=wordpress_plugin&utm_medium=register_link&utm_campaign=' . $version;
        $signUpText = __('Register here', 'optimonk');
        $registerText = sprintf(
            __("Don't have an account? %s", 'optimonk'),
            '<a class="optimonk-link" href="' . $registerUrl . '" target="_blank">' . $signUpText . '</a>'
        );
        $customVariablesDescription = __('The following custom variables can be used for visitor targeting.<br/>
                    <span class="underline">We store the following in custom variables:</span><br/>
                        <span class="bold">wp_utm_campaign, wp_utm_source, wp_utm_medium</span>: If they are given in the URL, then these are not deleted, they are overwritten.<br/>
                        <span class="bold">wp_source</span>: Contains the URL of the referral source, often coming from different domains, default: Direct.<br/>
                        <span class="bold">wp_referrer</span>: Contains the URL of the previous page, default: Direct.<br/>
                        <span class="bold">wp_visitor_type</span>: Role of the visitor on the site, example: administrator, default: logged out.<br/>
                        <span class="bold">wp_visitor_login_status</span>: The login status of the visitor on the site, values: logged out, logged in.<br/>
                        <span class="bold">wp_visitor_id</span>: The ID of the visitor on the site.<br/>
                        <span class="bold">wp_page_title</span>: Name of the current page.<br/>
                        <span class="bold">wp_post_type</span>: Type of post. If it cannot be defined, then: unknown.<br/>
                        <span class="bold">wp_post_type_with_prefix</span>: Type of post completed with a prefix. Prefix values: single, author, category, tag, day, month, year, time, date, tax.<br/>
                        <span class="bold">wp_post_categories</span>: Categories of the post, if there are more than one, then separated with: "|".<br/>
                        <span class="bold">wp_post_tags</span>: Tags of the post, if there are more than one, then separated with: "|".<br/>
                        <span class="bold">wp_post_author</span>: Author of the post.<br/>
                        <span class="bold">wp_post_full_date</span>: Exact and full date of the post. Based on the date format set in Settings -> General.<br/>
                        <span class="bold">wp_post_year</span>: Year of the post.<br/>
                        <span class="bold">wp_post_month</span>: Month of the post. For single-digit months, use a "0" prefix, example: September is "09".<br/>
                        <span class="bold">wp_post_day</span>: Day of the post. For single-digit days, use a "0" prefix, example: The first day of month it is "01".<br/>
                        <span class="bold">wp_is_front_page</span>: If the current page is the main page, the value is "1", for any other page the value is "0". The value "1" is assigned to the page set in Settings -> Reading -> "Front page displays".<br/>
                        <span class="bold">wp_search_query</span>: The expression searched for in any queries.<br/>
                        <span class="bold">wp_search_result_count</span>: Number of search results.<br/>
                    <p><span class="underline">Additional custom variables with WooCommerce plugin:</span></br>
                        <span class="bold">wp_cart_total_without_discounts</span>: Full price of cart, without discounts.<br/>
                        <span class="bold">wp_cart_total</span>: Final price of cart, with discounts.<br/>
                        <span class="bold">wp_number_of_item_kinds</span>: The number of different products.<br/>
                        <span class="bold">wp_total_number_of_cart_items</span>: The number of cart items.<br/>
                        <span class="bold">wp_applied_coupons</span>: Applied coupons, if there are more than one, then separated with: "|".<br/>
                        <span class="bold">wp_current_product.name</span>: Name of the product currently being viewed.<br/>
                        <span class="bold">wp_current_product.sku</span>: Item number of the product currently being viewed.<br/>
                        <span class="bold">wp_current_product.price</span>: Price of the product currently being viewed.<br/>
                        <span class="bold">wp_current_product.stock</span>: Stock or inventory level of the product currently being viewed, if it is set.<br/>
                        <span class="bold">wp_current_product.categories</span>: Categories of the product currently being viewed, if there are more than one, then separated with: "|".<br/>
                        <span class="bold">wp_current_product.tags</span>: Tags of the product currently being viewed, if there are more than one, then separated with: "|".
                    </p>', 'optimonk');

        $success = $this->getSuccessMessage();
        $error = $this->getErrorMessage();
        $pluginDirUrl = plugin_dir_url(self::$basePath);
        $pluginDirPath = plugin_dir_path(self::$basePath);
        $domain = $this->getSalesPageLink();
        $insertCodeImage = "assets/insert_code_en.png";
        if (get_bloginfo('language') == "hu") {
            $insertCodeImage = "assets/insert_code_hu.png";
        }
        wp_create_nonce('optimonk_setting');

        include(sprintf("%s/template/settings.php", dirname(__FILE__)));
    }

    public function postHandler()
    {
        if (! wp_verify_nonce($_POST['optimonk_setting_nonce'], 'optimonk_setting')) {
            wp_die(__('Invalid request.', 'optimonk'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'optimonk'));
        }

        $return = array();

        if (!is_numeric($_POST['accountId'])) {
            $return['error'] = '1';
        } else {
            $accountId = (int)$_POST['accountId'];
            $return['success'] = '1';
            update_option('optiMonk_accountId', $accountId);
        }

        wp_send_json($return);
    }

    protected function getSuccessMessage()
    {
        return __('Your data successfully updated!', 'optimonk');
    }

    protected function getErrorMessage()
    {
        return __('Wrong account id!', 'optimonk');
    }

    /**
     * @return string
     */
    protected function getSalesPageLink()
    {
        $accountId = get_option('optiMonk_accountId');
        $analytics = '';
        $domain = $this->getSalesDomain();

        if ($accountId) {
            $analytics = '/?utm_source=wordpress_plugin&utm_medium=logo&utm_campaign=' . $accountId;
        }

        return $domain . $analytics;
    }

    protected function getSalesDomain()
    {
        $locale = get_bloginfo('language');
        if ($locale === 'hu-HU') {
            return 'https://www.optimonk.hu';
        }

        return 'https://www.optimonk.com';
    }

    public function settings_javascript() { ?>
        <script type="text/javascript" >
            jQuery(document).ready(function ($) {
                var $updateField = $("#update-success");
                var $errorField = $("#update-error");
                var $optimonkAccountId = $("#optiMonk-accountId");
                var nonce = $("#optimonk_setting_nonce");

                $('#settings-form').submit(function (e) {
                    e.preventDefault();

                    $errorField.hide();
                    $updateField.hide();

                    $.ajax({
                        data: {
                            action: 'setting_form',
                            accountId: $optimonkAccountId.val(),
                            optimonk_setting_nonce: nonce.val(),
                        },
                        type: 'post',
                        url: ajaxurl,
                        success: function (response) {
                            if (response.success) {
                                $updateField.fadeIn();
                            }
                            if (response.error) {
                                $errorField.fadeIn();
                            }
                        }
                    });

                    var accountId = parseInt($optimonkAccountId.val(), 10);
                    if (accountId) {
                      $.ajax({
                        type: 'post',
                        url: "https://backend.optimonk.com/app/wordpress/connect",
                        crossDomain: true,
                        dataType: "json",
                        data: {
                          user: accountId,
                          domain: document.location.hostname
                        }
                      });
                    }
                });
            });
        </script> <?php
    }
}
