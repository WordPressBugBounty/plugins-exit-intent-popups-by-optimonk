<?php
require_once(dirname(__FILE__) . "/woo-version.php");

class OptiMonkWooDataInjector {
    protected $url = '';
    
    protected $postID = '';

    public function __construct($url) {
        $this->url = $url;
        $this->postID = url_to_postid($url);
    }

    public function getData() {
        $script = file_get_contents( dirname( __FILE__ ) . '/template/optimonk-woo-data.js' );


        $productData = $this->getProductData();
        $orderData = $this->getOrderData();

        $dataSet = array(
            'shop' => null, 'page' => null,'product' => null, 'order' => null
        );

        $dataSet = array_merge($dataSet, array('shop' => array(
            'pluginVersion' => OM_PLUGIN_VERSION,
            'platform' => $this->getPlatform(),
        )));

        $dataSet = array_merge($dataSet, array('page' => array(
            'postId' => $this->postID,
            'postType' => $this->getPostType(),
        )));

        if (!empty($productData) && $productData['current_product.id']) {
            $dataSet = array_merge($dataSet, array('product' => self::removePrefixes($productData)));
        }
        if (!empty($orderData) && $orderData['order.order_id']) {
            $dataSet = array_merge($dataSet, array('order' => self::removePrefixes($orderData)));
        }

        $script = str_replace(
            '{{set_variables}}',
            'window.WooDataForOM = ' . self::getDataSet($dataSet). ';',
            $script
        );

        $script = str_replace(
            '{{set_order_data}}',
            $orderData && $orderData['order.order_id'] ? 
                'orderData = ' . self::getDataSet($orderData). ';' :
                '',
            $script
        );

        return $script . "\n";
    }

    protected function getProductData() {
        $postID = $this->postID;

        if ($this->isWooCommerceProductPage() === false) {
            return array();
        }

        $return = array(
            'current_product.id'           => '',
            'current_product.name'         => '',
            'current_product.sku'          => '',
            'current_product.price'        => '',
            'current_product.stock'        => '',
            'current_product.categories'   => '',
            'current_product.category_ids' => [],
            'current_product.tags'         => '',
            'current_product.is_in_stock'  => false,
        );

        $product = wc_get_product($postID);
        $productId = $product->get_id();

        $return['current_product.id']           = $productId;
        $return['current_product.name']         = $product->get_title();
        $return['current_product.sku']          = $product->get_sku();
        $return['current_product.price']        = $product->get_price();
        $return['current_product.stock']        = $product->get_stock_quantity();
        $return['current_product.category_ids'] = $product->get_category_ids();
        $return['current_product.is_in_stock']  = $product->is_in_stock();
        $return['current_product.categories']   = strip_tags( wc_get_product_category_list($productId, '|') );
        $return['current_product.tags']         = strip_tags( wc_get_product_tag_list($productId, '|') );

        return $return;
    }

    protected function getOrderData() {
        $url = $this->url;

        $return = array();
    
        if (!$url || strpos($url, 'order-received/') === false) {
            return $return;
        }
    
        $parsed_url = parse_url($url);
        $query = [];
        if (!isset($parsed_url['query'])) {
            return $return;
        }
    
        parse_str($parsed_url['query'], $query);
    
        $key = isset($query['key']) ? sanitize_text_field($query['key']) : '';
        if (!$key) {
            return $return;
        }

        $order_id = wc_get_order_id_by_order_key($key);
        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $key) {
            return $return;
        }
    
        $return['order.order_id']     = $order->get_id();
        $return['order.total']        = $order->get_total();
        $return['order.currency']     = $order->get_currency();
        $return['order.item_count']   = $order->get_item_count();
        $return['order.order_number'] = $order->get_order_number();

        $product_ids = array();
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id) {
                $product_ids[] = $product_id;
            }
        }
        $return['order.order_product_ids'] = $product_ids;

        return $return;
    }

    protected function isWooCommerceProductPage() {
        return WooVersion::isWooCommerce() && $this->postID !== 0 && $this->getPostType() === 'product';
    }

    protected function getPostType() {
        return get_post_type(get_post($this->postID));
    }

    protected function getPlatform() {
        return WooVersion::isWooCommerce() ? 'woocommerce' : 'wordpress';
    }

    protected static function getDataSet( array $data ) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!$json) return 'null';
        return $json;
	}

    protected static function removePrefixes(array $data): array {
        $prefixes = ['order.', 'current_product.'];
        $result = [];
        foreach ($data as $key => $value) {
            $newKey = $key;
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $newKey = substr($key, strlen($prefix));
                    break;
                }
            }
            $result[$newKey] = $value;
        }
        return $result;
    }

}