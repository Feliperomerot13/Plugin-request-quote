<?php
if (!defined('ABSPATH')) {
    exit;
}

class Luma_Quote_Cart_Ajax
{

    public function __construct()
    {
        $actions = array(
            'lqc_add_to_quote',
            'lqc_update_cart_item',
            'lqc_remove_cart_item',
            'lqc_submit_quote',
            'lqc_get_fragments',
        );

        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, array($this, $action));
            add_action('wp_ajax_nopriv_' . $action, array($this, $action));
        }
    }

    /**
     * Add product to quote.
     */
    public function lqc_add_to_quote()
    {
        check_ajax_referer('lqc-nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        $variations = array();
        if (isset($_POST['variations']) && is_array($_POST['variations'])) {
            foreach ($_POST['variations'] as $key => $val) {
                $variations[sanitize_text_field($key)] = sanitize_text_field($val);
            }
        }

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product.', 'luma-quote-cart')));
        }

        $cart = Luma_Quote_Cart::get_instance()->cart;
        $added = $cart->add_to_cart($product_id, $quantity, $variation_id, $variations);

        if (is_wp_error($added)) {
            wp_send_json_error(array('message' => $added->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('¡Producto agregado a la cotización!', 'luma-quote-cart'),
            'count' => $cart->get_cart_count(),
            'html' => Luma_Quote_Cart::get_instance()->frontend->get_cart_html(),
        ));
    }

    /**
     * Update item quantity.
     */
    public function lqc_update_cart_item()
    {
        check_ajax_referer('lqc-nonce', 'nonce');

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        if (!$cart_item_key && isset($_POST['product_id'])) {
            $cart_item_key = sanitize_text_field($_POST['product_id']);
        }
        
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $variations = array();
        if (isset($_POST['variations']) && is_array($_POST['variations'])) {
            foreach ($_POST['variations'] as $key => $val) {
                $variations[sanitize_text_field($key)] = sanitize_text_field($val);
            }
        }

        $cart = Luma_Quote_Cart::get_instance()->cart;
        
        if (!empty($variations) || $variation_id) {
            $cart->update_item_variations($cart_item_key, $variation_id, $variations, $quantity);
        } else {
            $cart->update_quantity($cart_item_key, $quantity);
        }

        wp_send_json_success(array(
            'message' => __('Cart updated.', 'luma-quote-cart'),
            'count' => $cart->get_cart_count(),
            'html' => Luma_Quote_Cart::get_instance()->frontend->get_cart_html(),
        ));
    }

    /**
     * Remove item.
     */
    public function lqc_remove_cart_item()
    {
        check_ajax_referer('lqc-nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        $cart = Luma_Quote_Cart::get_instance()->cart;
        $cart->remove_item($product_id);

        wp_send_json_success(array(
            'message' => __('Item removed.', 'luma-quote-cart'),
            'count' => $cart->get_cart_count(),
            'details' => $cart->get_cart_details(),
        ));
    }

    /**
     * Submit Quote Form.
     */
    public function lqc_submit_quote()
    {
        check_ajax_referer('lqc-nonce', 'nonce');

        $data = array();
        $fields = array('lqc_name', 'lqc_company', 'lqc_email', 'lqc_phone', 'lqc_message');

        foreach ($fields as $field) {
            $data[$field] = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
        }

        if (empty($data['lqc_name']) || empty($data['lqc_phone'])) {
            wp_send_json_error(array('message' => __('Name and Phone/WhatsApp are required.', 'luma-quote-cart')));
        }

        $mailer = Luma_Quote_Cart::get_instance()->mailer;
        $sent = $mailer->send_quote($data);

        if ($sent) {
            Luma_Quote_Cart::get_instance()->cart->clear_cart();
            $success_message = get_option('lqc_success_message', __('Quote requested successfully!', 'luma-quote-cart'));
            wp_send_json_success(array('message' => $success_message));
        } else {
            wp_send_json_error(array('message' => __('Failed to send email. Please try again.', 'luma-quote-cart')));
        }
    }

    /**
     * Get cart fragments (HTML and count) bypassing cache.
     */
    public function lqc_get_fragments()
    {
        // Don't check nonce here to allow cached pages to fetch fresh fragments unconditionally
        $cart = Luma_Quote_Cart::get_instance()->cart;
        wp_send_json_success(array(
            'count' => $cart->get_cart_count(),
            'html' => Luma_Quote_Cart::get_instance()->frontend->get_cart_html(),
            'nonce' => wp_create_nonce('lqc-nonce')
        ));
    }
}
