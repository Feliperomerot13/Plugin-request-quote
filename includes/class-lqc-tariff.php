<?php
if (!defined('ABSPATH')) { exit; }

/** Private quote rates are intentionally separate from WooCommerce storefront prices. */
final class LQC_Tariff
{
    private static function entry($parent_id)
    {
        static $rates = null;
        if ($rates === null) {
            $file = __DIR__.'/site-tariffs.php';
            $rates = is_file($file) ? require $file : array();
        }
        return $rates[$parent_id] ?? array();
    }

    public static function unit($product)
    {
        if (!$product) { return 'piece'; }
        $explicit = $product->get_meta('_lqc_unit');
        if ($explicit === 'm2') { return 'm2'; }
        if ($explicit === 'piece') { return 'piece'; }
        $entry = self::entry($product->get_parent_id() ?: $product->get_id());
        return $entry['unit'] ?? 'piece';
    }

    public static function price($product, $item)
    {
        $net = $product->get_meta('_lqc_net');
        $tax = $product->get_meta('_lqc_tax');
        if ($net !== '' || $tax !== '') {
            return ctype_digit((string)$net) && (int)$net > 0 && ctype_digit((string)$tax)
                ? array((int)$net, (int)$tax) : array(null, null);
        }
        $entry = self::entry($product->get_id());
        if (!isset($entry['net'], $entry['tax']) || $entry['parent'] !== (int)$item['product_id']) {
            return array(null, null);
        }
        if (!empty($entry['color'])) {
            $attributes = $product->get_variation_attributes();
            $color = $attributes['attribute_color'] ?? ($attributes['attribute_pa_color'] ?? '');
            if (sanitize_title($color) !== $entry['color']) { return array(null, null); }
        }
        return array($entry['net'], $entry['tax']);
    }
}
