<?php
class WcAttributes {
    public static function getCartVariables() {
        echo json_encode(self::getWooCommerceCartData());
    }

    public static function getVariables( $url ) {
        $postID = url_to_postid($url);
        $productData    = self::getWooCommerceProductData($postID);
        echo json_encode($productData);
    }

    protected static function getWooCommerceCartData() {
        global $woocommerce;

        $return = array(
            'cart' => array(),
            'avs'  => array(
                'cart_total'                   => 0,
                'cart_total_without_discounts' => 0,
                'number_of_item_kinds'         => 0,
                'total_number_of_cart_items'   => 0,
                'applied_coupons'              => ''
            )
        );

        if ( self::isWooCommerce() === false ) {
            return $return;
        }

        $total                      = 0;
        $number_of_item_kinds       = 0;
        $total_number_of_cart_items = 0;
        $WC = WC();

        foreach ( $WC->cart->get_cart() as $cart_item_key => $cart_item ) {
            /** @var WC_Product_Simple $product */
            $product            = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $product_name       = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key );
            $quantity           = $cart_item['quantity'];
            $line_subtotal      = $cart_item['line_subtotal'];
            $line_subtotal_tax  = $cart_item['line_subtotal_tax'];
            $item_price         = $line_subtotal / $quantity;
            $item_tax           = $line_subtotal_tax / $quantity;
            $price              = $item_price + $item_tax;

            $identifier = $product->get_sku() ? $product->get_sku() : $product->get_id();

            $return['cart'][] = array(
                'sku'      => $identifier,
                'name'     => $product_name,
                'price'    => $price,
                'quantity' => $quantity,
            );
            $number_of_item_kinds++;
            $total_number_of_cart_items += $quantity;
            $total                      += $quantity * $price;
        }

        $return['avs']['cart_total_without_discounts'] = $total;
        $return['avs']['cart_total']                   = (float) $WC->cart->total;
        $return['avs']['number_of_item_kinds']         = $number_of_item_kinds;
        $return['avs']['total_number_of_cart_items']   = $total_number_of_cart_items;
        $return['avs']['applied_coupons']              = join( '|', $woocommerce->cart->get_applied_coupons() );

        return $return;
    }

    protected static function getWooCommerceProductData($postID) {
        $return = array(
            'current_product.name'       => '',
            'current_product.sku'        => '',
            'current_product.price'      => '',
            'current_product.stock'      => '',
            'current_product.categories' => '',
            'current_product.tags'       => '',
        );

        if ( self::isWooCommerceProductPage(get_post($postID)) === false ) {
            return $return;
        }

        $woocommerceVersion = self::wpbo_get_woo_version_number();
        $product = wc_get_product($postID);

        $return['current_product.name']       = $product->get_title();
        $return['current_product.sku']        = $product->get_sku();
        $return['current_product.price']      = $product->get_price();
        $return['current_product.stock']      = $product->get_stock_quantity();

        if ($woocommerceVersion >= "3.0.2") {
            $productId = self::getProductId($product);
            $return['current_product.categories'] = strip_tags( wc_get_product_category_list( $productId, '|' ) );
            $return['current_product.tags']       = strip_tags( wc_get_product_tag_list( $productId, '|' ) );
        } else {
            $return['current_product.categories'] = strip_tags( $product->get_categories( '|' ) );
            $return['current_product.tags']       = strip_tags( $product->get_tags( '|' ) );
        }

        return $return;
    }

    /**
     * @param $product
     * @return mixed
     */
    protected static function getProductId($product)
    {
        $woocommerceVersion = self::wpbo_get_woo_version_number();
        return $woocommerceVersion >= '2.6.0' ? $product->get_id() : $product->id;
    }

    protected static function isWooCommerceProductPage($post) {
        return self::isWooCommerce() && get_post_type($post) === 'product';
    }

    protected static function isWooCommerce() {
        global $woocommerce;

        if (
            isset( $woocommerce ) === false
            || class_exists( '\WC_Product' ) === false
        ) {
            return false;
        }

        return true;
    }

    protected static function wpbo_get_woo_version_number() {
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';

        // If the plugin version number is set, return it
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
            // Otherwise return null
            return NULL;
        }
    }
}
