<?php
defined('ABSPATH') || exit;

final class LQC_Storefront
{
    public static function setup()
    {
        if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('lumaa.co', 'www.lumaa.co'), true)) {
            return;
        }
        if (is_shop() || is_product_taxonomy()) {
            add_filter('woocommerce_catalog_orderby', array(__CLASS__, 'sort_options'), 999);
            add_filter('gettext_betheme', array(__CLASS__, 'catalog_text'), 20, 2);
            add_filter('gettext_woocommerce', array(__CLASS__, 'catalog_text'), 20, 2);
        }
        if (is_product()) {
            add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'gallery_priority'), 99, 2);
            add_filter('script_loader_tag', array(__CLASS__, 'viewer_loader'), 999, 3);
        }
    }

    public static function sort_options($options)
    {
        unset($options['price'], $options['price-desc']);
        foreach (array('date'=>'Más recientes', 'title'=>'Nombre de la A a la Z') as $key=>$label) {
            if (isset($options[$key])) {
                $options[$key] = $label;
            }
        }
        return $options;
    }

    public static function catalog_text($translation, $original)
    {
        $labels = array('Load more'=>'Ver más productos', 'Shop order'=>'Ordenar productos');
        return $labels[$original] ?? $translation;
    }

    public static function gallery_priority($attributes, $attachment)
    {
        if (!isset($attributes['data-large_image'])) {
            return $attributes;
        }
        if ((int)$attachment->ID === (int)get_post_thumbnail_id(get_queried_object_id())) {
            $attributes['fetchpriority'] = 'high';
            $attributes['loading'] = 'eager';
        }
        return $attributes;
    }

    public static function viewer_loader($tag, $handle, $src)
    {
        if ($handle !== 'bp3d-lib-model-viewer' || !class_exists('WP_HTML_Tag_Processor')) {
            return $tag;
        }
        $script = new WP_HTML_Tag_Processor($tag);
        if (!$script->next_tag('script') || $script->get_attribute('type') !== 'module') {
            return $tag;
        }
        // Keep the vendor URL and attributes, including CSP and cross-origin policy.
        $original_src = $script->get_attribute('src');
        if (!$original_src) {
            return $tag;
        }
        $script->set_attribute('data-luma-module', $original_src);
        $script->set_attribute('src', LUMA_QUOTE_URL.'assets/js/luma-model-viewer.js?ver='.LUMA_QUOTE_VERSION);
        $script->remove_attribute('type');
        if ($script->get_attribute('integrity')) {
            $script->set_attribute('data-luma-integrity', $script->get_attribute('integrity'));
            $script->remove_attribute('integrity');
        }
        return $script->get_updated_html();
    }
}
