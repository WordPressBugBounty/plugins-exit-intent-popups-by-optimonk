<?php
require_once(dirname(__FILE__) . "/optimonk-woo-data-injector.php");

/**
 * @Class OptiMonkFront
 */

class OptiMonkFront
{
    public function __construct()
    {
        add_filter('query_vars', array($this, 'addQueryVars'));
        add_action('wp', array($this, 'parseRequest'));
    }

    public static function init()
    {
        $accountId = (int) get_option('optiMonk_accountId');
        $templateBasePath = dirname(__FILE__) . '/template';

        if (!$accountId) {
            return;
        }

        list($pageData, $basicData) = self::splitPageAttributes(OptiMonkFront::getWordpressData());
        $insertJavaScript = file_get_contents($templateBasePath . '/insert-code.js');
        $insertJavaScript = str_replace('{{basic_data}}', json_encode($basicData), $insertJavaScript);

        $url = get_site_url();
        $url = preg_replace('(^http:|^https:)', '', $url);

        $insertJavaScript = str_replace('{{siteUrl}}', $url, $insertJavaScript);


		$current_url = self::getCurrentUrl();
        $dataInjector = new OptiMonkWooDataInjector(urldecode($current_url));
		$dataToInsert = $dataInjector->getData();
        $dataToInsert = str_replace('{{page_data}}', json_encode($pageData), $dataToInsert);

        $frontDomain = OPTIMONK_FRONT_DOMAIN;

        echo <<<EOD
<script type="text/javascript">
    $insertJavaScript
    $dataToInsert
</script>
<script type="text/javascript" src="https://$frontDomain/script.js?account=$accountId" async></script>
EOD;
    }

    /**
     * The attributes that describe the page being viewed. These go to the
     * optimonk-woo-data.js template, which writes them on every page load: the campaign
     * init listeners in insert-code.js do not fire on a page no campaign is sent to, so
     * these used to keep describing the previously viewed page.
     *
     * Everything else stays with insert-code.js on purpose. The utm_* keys are only
     * present when they are in the query string, which is what keeps the attribution
     * alive across page loads, and rewriting the visitor and source attributes on every
     * page load would be a behaviour change of its own.
     */
    protected static $pageAttributes = array(
        'page_title',
        'post_type',
        'post_type_with_prefix',
        'post_categories',
        'post_tags',
        'post_author',
        'post_full_date',
        'post_year',
        'post_month',
        'post_day',
        'is_front_page',
        'is_home',
        'search_query',
        'search_results_count',
    );

    protected static function splitPageAttributes(array $data)
    {
        $pageData = array();

        foreach (self::$pageAttributes as $key) {
            if (array_key_exists($key, $data)) {
                $pageData[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return array($pageData, $data);
    }

    protected static function getWordpressData()
    {
        global $current_user, $wp_query;
        $return = array();

        if (isset($_GET['utm_campaign'])) {
            $return['utm_campaign'] = self::escapeSlashes($_GET['utm_campaign']);
        }

        if (isset($_GET['utm_medium'])) {
            $return['utm_medium'] = self::escapeSlashes($_GET['utm_medium']);
        }

        if (isset($_GET['utm_source'])) {
            $return['utm_source'] = self::escapeSlashes($_GET['utm_source']);
        }

        $return['source'] = 'Direct';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $return['referrer'] = $referrer ? $referrer : 'Direct';

        if ($referrer && strpos($referrer, get_site_url()) !== 0) {
            $return['source'] = $referrer;
        }

        wp_get_current_user();
        $return['visitor_type'] = (!empty($current_user->roles[0]) ? $current_user->roles[0] : 'logged out');

        $return['visitor_login_status'] = 'logged out';
        if (is_user_logged_in()) {
            $return['visitor_login_status'] = 'logged in';
        }

        $return['visitor_id'] = 0;
        $userId = get_current_user_id();
        if ($userId > 0) {
            $return['visitor_id'] = $userId;
        }

        $return['page_title'] = strip_tags(wp_title("|", false, "right"));
        $return['post_type'] = get_post_type() ? get_post_type() : 'unknown';
        $return['post_type_with_prefix'] = '';

        $return['post_categories'] = '';
        $return['post_tags'] = '';
        $return['post_author'] = '';
        $return['post_full_date'] = '';
        $return['post_year'] = '';
        $return['post_month'] = '';
        $return['post_day'] = '';

        if (is_singular()) {
            $return['post_type'] = get_post_type();
            $return['post_type_with_prefix'] = 'single ' . get_post_type();

            $categories = array();
            foreach (get_the_category() as $category) {
                $categories[] = $category->slug;
            }

            $return['post_categories'] = join('|', $categories);

            if ($postTags = get_the_tags()) {
                $tags = array();
                foreach ($postTags as $tag) {
                    $tags[] = $tag->slug;
                }

                $return['post_tags'] = join('|', $tags);
            }

            if (($author = get_userdata($GLOBALS["post"]->post_author))) {
                $return['post_author'] = $author->display_name;
            }

            $return["post_full_date"] = get_the_date();
            $return["post_year"] = get_the_date("Y");
            $return["post_month"] = get_the_date("m");
            $return["post_day"] = get_the_date("d");
        }

        if (is_archive() || is_post_type_archive()) {
            $return['post_type'] = get_post_type();
            $typePrefix = '';

            if (is_author()) {
                $typePrefix = "author ";
            } elseif (is_category()) {
                $typePrefix = "category ";
            } elseif (is_tag()) {
                $typePrefix = "tag ";
            } elseif (is_day()) {
                $typePrefix = "day ";
            } elseif (is_month()) {
                $typePrefix = "month ";
            } elseif (is_year()) {
                $typePrefix = "year ";
            } elseif (is_time()) {
                $typePrefix = "time ";
            } elseif (is_date()) {
                $typePrefix = "date ";
            } elseif (is_tax()) {
                $typePrefix = "tax ";
            }

            $return['post_type_with_prefix'] = $typePrefix . $return['post_type'];
        }

        $return['is_front_page'] = 0;
        if (is_front_page()) {
            $return['is_front_page'] = 1;
        }

        $return['is_home'] = 1;
        if (!is_front_page() && is_home()) {
            $return['is_home'] = 1;
        }

        $return['search_query'] = '';
        $return["search_results_count"] = 0;
        if (is_search()) {
            $return["search_query"] = get_search_query();
            $return["search_results_count"] = $wp_query->post_count;
        }

        return $return;
    }

    function addQueryVars($vars) {
        $vars[] = 'plugin';
        $vars[] = 'action';
        return $vars;
    }

    function parseRequest($wp) {

        $pluginVar = array_key_exists('plugin', $wp->query_vars);
        $actionVar = array_key_exists('action', $wp->query_vars);

        if ($pluginVar && $actionVar &&
            $wp->query_vars['plugin'] == 'optimonk' &&
            $wp->query_vars['action'] == 'cartData') {
            WcAttributes::getCartVariables();
            die();
        }
        if ($pluginVar && $actionVar &&
            $wp->query_vars['plugin'] == 'optimonk' &&
            $wp->query_vars['action'] == 'productData') {
            $url = isset($_POST['url']) ? urldecode($_POST['url']) : '';
            WcAttributes::getVariables($url);
            die();
        }

       if ($pluginVar && $actionVar &&
         $wp->query_vars['plugin'] == 'optimonk' &&
         $wp->query_vars['action'] == 'addToCart') {

         WcAttributes::addToCart();
         die();
       }
    }

    /**
     * The URL of the page being rendered.
     *
     * REQUEST_URI already contains the install subdirectory (and any directory-based
     * language segment), so it must not be passed through home_url() -- that prefixes
     * the same path a second time and breaks every URL-based lookup, url_to_postid()
     * included. Scheme and host still come from home_url() so the URL stays canonical.
     */
    protected static function getCurrentUrl()
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        if (!$requestUri) {
            return home_url();
        }

        $home = parse_url(home_url());

        if (empty($home['scheme']) || empty($home['host'])) {
            return home_url($requestUri);
        }

        $origin = $home['scheme'] . '://' . $home['host'];
        if (!empty($home['port'])) {
            $origin .= ':' . $home['port'];
        }

        return $origin . $requestUri;
    }

    protected static function escapeSlashes($string)
    {
        return str_replace("/", "\/", $string);
    }
}
