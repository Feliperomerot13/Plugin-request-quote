<?php
if (!defined('ABSPATH')) {
    exit;
}

class Luma_Quote_Cart_Cart
{

    private $session_key = 'luma_quote_cart';

    public function __construct()
    {
        // Initialize session if needed (Usually WC handles this, but we ensure hooks are ready)
    }

    /**
     * Get cart items from session.
     *
     * @return array
     */
    public function get_cart_items()
    {
        if (!WC()->session) {
            return array();
        }
        return (array) WC()->session->get($this->session_key, array());
    }

    /**
     * Add item to quote cart.
     *
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param array $variations
     * @return bool|WP_Error
     */
    public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variations = array())
    {
        if (!WC()->session) {
            return new WP_Error('no_session', __('WooCommerce session not available.', 'luma-quote-cart'));
        }

        $parent = wc_get_product($product_id);
        if (!$parent || $parent->get_status() !== 'publish') {
            return new WP_Error('invalid_product', 'Esta referencia ya no está disponible.');
        }
        if (!$variation_id && !empty($variations)) {
            $data_store = WC_Data_Store::load('product');
            $match = $data_store->find_matching_product_variation(wc_get_product($product_id), $variations);
            if ($match) $variation_id = $match;
        }

        if ($parent->is_type('variable') && !$variation_id) {
            return new WP_Error('variation_missing', 'Elige las opciones de la referencia.');
        }
        if ($variation_id) {
            $variant = wc_get_product($variation_id);
            if (!$variant || !$variant->is_type('variation') || $variant->get_parent_id() !== $parent->get_id() || !$variant->variation_is_active()) {
                return new WP_Error('invalid_variation', 'Elige una combinación disponible.');
            }
        }
        $cart = $this->get_cart_items();
        $cart_item_key = $variation_id ? $product_id . '_' . $variation_id : $product_id;

        if (isset($cart[$cart_item_key])) {
            $cart[$cart_item_key]['quantity'] += $quantity;
        } else {
            $cart[$cart_item_key] = array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'variations' => $variations,
                'quantity' => $quantity,
                'added_at' => current_time('timestamp'),
            );
        }

        WC()->session->set($this->session_key, $cart);
        return true;
    }

    /**
     * Update item quantity.
     *
     * @param string $cart_item_key
     * @param int $quantity
     */
    public function update_quantity($cart_item_key, $quantity)
    {
        if (!WC()->session) {
            return;
        }

        $cart = $this->get_cart_items();

        if (isset($cart[$cart_item_key])) {
            if ($quantity > 0) {
                $cart[$cart_item_key]['quantity'] = $quantity;
            } else {
                unset($cart[$cart_item_key]);
            }
            WC()->session->set($this->session_key, $cart);
        }
    }

    /**
     * Update item variations and rebuild key.
     */
    public function update_item_variations($cart_item_key, $variation_id, $variations, $quantity)
    {
        if (!WC()->session) return;
        $cart = $this->get_cart_items();

        if (isset($cart[$cart_item_key])) {
            $item = $cart[$cart_item_key];
            $product_id = $item['product_id'];
            
            // Try to find matching variation ID if 0
            if (!$variation_id) {
                $data_store = WC_Data_Store::load('product');
                $match = $data_store->find_matching_product_variation(wc_get_product($product_id), $variations);
                if (!$match) {
                    return new WP_Error('invalid_variation', 'Esta combinación no está disponible.');
                }
                $variation_id = $match;
            }
            $variant = wc_get_product($variation_id);
            if (!$variant || !$variant->is_type('variation') || (int)$variant->get_parent_id() !== (int)$product_id || !$variant->variation_is_active()) {
                return new WP_Error('invalid_variation', 'Esta combinación no está disponible.');
            }

            $new_cart_item_key = $variation_id ? $product_id . '_' . $variation_id : $product_id;

            if ($quantity <= 0) {
                unset($cart[$cart_item_key]);
            } else if ($new_cart_item_key !== $cart_item_key) {
                unset($cart[$cart_item_key]);
                if (isset($cart[$new_cart_item_key])) {
                    $cart[$new_cart_item_key]['quantity'] += $quantity;
                } else {
                    $item['variation_id'] = $variation_id;
                    $item['variations'] = $variations;
                    $item['quantity'] = $quantity;
                    $cart[$new_cart_item_key] = $item;
                }
            } else {
                $cart[$cart_item_key]['variation_id'] = $variation_id;
                $cart[$cart_item_key]['variations'] = $variations;
                $cart[$cart_item_key]['quantity'] = $quantity;
            }
            
            WC()->session->set($this->session_key, $cart);
        }
    }

    /**
     * Remove item from cart.
     *
     * @param string $cart_item_key
     */
    public function remove_item($cart_item_key)
    {
        $this->update_quantity($cart_item_key, 0);
    }

    /**
     * Clear the cart.
     */
    public function clear_cart()
    {
        if (WC()->session) {
            WC()->session->set($this->session_key, array());
        }
    }

    /**
     * Get cart count.
     *
     * @return int
     */
    public function get_cart_count()
    {
        $cart = $this->get_cart_items();
        return count($cart);
    }

    /**
     * Get formatted cart details for email/display.
     *
     * @return array
     */
    public function get_cart_details()
    {
        $cart = $this->get_cart_items();
        $details = array();

        foreach ($cart as $cart_item_key => $item) {
            $product_id = isset($item['variation_id']) && $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id'];
            $product = wc_get_product($product_id);

            if ($product) {
                $variations_formatted = array();
                if (!empty($item['variations'])) {
                    foreach ($item['variations'] as $name => $val) {
                        $label = wc_attribute_label(str_replace('attribute_', '', $name), $product);
                        $variations_formatted[] = $label . ': ' . $val;
                    }
                }

                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');

                $details[] = array(
                    'cart_key' => $cart_item_key,
                    'product_id' => $item['product_id'],
                    'variation_id' => isset($item['variation_id']) ? $item['variation_id'] : 0,
                    'name' => $product->get_name(),
                    'permalink' => $product->get_permalink(),
                    'sku' => $product->get_sku(),
                    'image' => $product->get_image(),
                    'image_url' => $image_url,
                    'quantity' => $item['quantity'],
                    'unit' => $product->get_meta('_lqc_unit') === 'm2' ? 'm²' : 'ud.',
                    'variations' => $variations_formatted,
                    'raw_variations' => isset($item['variations']) ? $item['variations'] : array()
                );
            }
        }

        return $details;
    }
}
