<?php
/**
 * OptiMonk_Notification
 *
 * @author Varró Dániel
 * @since 2017.06.22. 14:47
 */

class OptiMonk_Notification
{

    /**
     * @var string
     */
    protected static $pluginLink = 'themes.php?page=optimonk';

    const USER_META_DISMISSED = 'optimonk-remove-notice';

    const OPTION_NAME = 'optimonk_notice';

    /** @var array */
    protected $options;

    /**
     * Sets the options, because they always have to be there on instance.
     */
    public function __construct()
    {
        $this->options = $this->get_options();
        add_action('admin_footer', array($this, 'notifications_javascript'));
        add_action('wp_ajax_dismiss_om_notice', array($this, 'dismiss_handler'));
        $this->set_up_notice_start_time();
    }

    /**
     * Checks if the notice should be added or removed.
     */
    public function initialize()
    {
        if ($this->is_notice_dismissed()) {
            return;
        }
        if ($this->should_add_notification()) {
            $this->add_notification();
        }
    }

    /**
     * Sets the upgrade notice.
     */
    public function set_up_notice_start_time()
    {

        if (!$this->has_first_activated_on()) {
            $this->set_first_activated_on();
        }
    }

    public function isEnabled()
    {
        return $this->should_add_notification() && !$this->is_notice_dismissed();
    }

    /**
     * When the notice should be shown.
     *
     * @return bool
     */
    protected function should_add_notification()
    {
        return ($this->options['first_activated_on'] < strtotime('-2weeks'));
    }

    /**
     * Checks if the options has a first activated on date value.
     */
    protected function has_first_activated_on()
    {
        return $this->options['first_activated_on'] !== null;
    }

    /**
     * Sets the first activated on.
     */
    protected function set_first_activated_on()
    {
        $this->options['first_activated_on'] = strtotime('now');

        $this->save_options();
    }

    /**
     * Adds a notification to the notification center.
     */
    public function add_notification()
    {
        add_action('optimonk_all_admin_notices', array($this, 'get_notification'));
        add_action('wp_before_admin_bar_render', array($this, 'admin_tool_bar'));
    }

    /**
     * Gets the notification value.
     *
     */
    public function get_notification()
    {
        echo sprintf('
        <div id="om-feedback" class="notice om-notice">
		<h3 class="om-notification"><span class="dashicons dashicons-flag"></span>' . __('Notifications', 'optimonk') . '</h3>
        <div class="notice om-alert">
        <p>' . __('We have noticed you\'ve been using OptiMonk for some time now. We hope you love it! <br>
We would be thrilled if you could <a href="https://hu.wordpress.org/plugins/exit-intent-popups-by-optimonk/#reviews" target="_blank">give us a 5 stars rating on Wordpress.org</a>!
We\'re happy to learn more about your business and we can help you get the most out of your website. Schedule a free consultation by clicking <a href="https://calendly.com/optimonk-session" target="_blank">here</a>!', 'optimonk') . '</p>
        <a id="om-hide-notification" class="om-message-close notice-dismiss" href="">' . __('Hide', 'optimonk') . '</a>
        </div>
        <div style="clear: both"></div>
        </div>');
    }

    /**
     * Dismisses the notice.
     *
     * @return string
     */
    protected function is_notice_dismissed()
    {
        return get_user_meta(get_current_user_id(), self::USER_META_DISMISSED, true) === '1';
    }

    public function dismiss_handler()
    {
        if ($this->dismiss_notice()) {
            wp_send_json(array("success" => 1));
        }
    }

    /**
     * Dismisses the notice.
     */
    private function dismiss_notice()
    {
        return update_user_meta(get_current_user_id(), self::USER_META_DISMISSED, true);
    }

    /**
     * Returns the set options
     *
     * @return mixed|void
     */
    protected function get_options()
    {
        return get_option(self::OPTION_NAME);
    }

    public function admin_tool_bar($wp_admin_bar)
    {
        global $wp_admin_bar;
        $counter = sprintf('<div class="om-counter"><span aria-hidden="true">%d</span></div>', 1);
        $title = '<div class="wp-monk svg"><span class="screen-reader-text">' . __('Review the plugin', 'optimonk') . '</span></div>';

        $wp_admin_bar->add_menu(array(
            'id' => 'optimonk-menu',
            'title' => $title . $counter,
            'href' => self::$pluginLink,
            'meta' => array('tabindex' => 0),
        ));

        $wp_admin_bar->add_menu(
            array(
                'parent' => 'optimonk-menu',
                'id' => 'optimonk-notification',
                'title' => __('Notifications', 'optimonk') . " " . $counter,
                'href' => self::$pluginLink,
                'meta' => array('tabindex' => 0),
            )
        );
    }

    /**
     * Saves the options to the database.
     */
    protected function save_options()
    {
        update_option(self::OPTION_NAME, $this->options);
    }

    public function notifications_javascript()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $("#om-hide-notification").on('click', function (e) {
                    e.preventDefault();

                    $.ajax({
                        data: {
                            action: 'dismiss_om_notice'
                        },
                        type: 'post',
                        url: ajaxurl,
                        success: function (response) {
                            if (response.success) {
                                $("#om-feedback").fadeOut();
                                $("#wp-admin-bar-optimonk-menu").fadeOut();
                                $("#om-notification-bubble").fadeOut();
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
