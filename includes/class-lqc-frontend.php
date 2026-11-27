<?php
if (!defined('ABSPATH')) {
    exit;
}

class Luma_Quote_Cart_Frontend
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Button hooks
        add_action('woocommerce_after_shop_loop_item', array($this, 'add_button_loop'), 15);
        add_action('woocommerce_single_product_summary', array($this, 'add_button_single'), 35);

        // Remove Add to Cart if setting enabled
        if ('yes' === get_option('lqc_replace_add_to_cart')) {
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        }

        // Floating Basket / Modal
        add_action('wp_footer', array($this, 'render_popup'));

        // Shortcodes
        add_shortcode('luma_quote_button', array($this, 'shortcode_button'));
        add_shortcode('luma_quote_basket', array($this, 'shortcode_basket'));
        add_shortcode('luma_quote_cart', array($this, 'shortcode_cart'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('luma-quote-cart', LUMA_QUOTE_URL . 'assets/css/luma-quote.css', array(), LUMA_QUOTE_VERSION);
        wp_enqueue_script('luma-quote-cart', LUMA_QUOTE_URL . 'assets/js/luma-quote.js', array('jquery'), LUMA_QUOTE_VERSION, true);

        wp_localize_script('luma-quote-cart', 'lqc_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'quote_page_url' => get_option('lqc_quote_page_id') ? get_permalink(get_option('lqc_quote_page_id')) : '',
            'pdf_enabled' => LQC_Quote_PDF::enabled(),
            'nonce' => wp_create_nonce('lqc-nonce'),
            'strings' => array(
                'added' => __('¡Agregado!', 'luma-quote-cart'),
                'error' => __('Error', 'luma-quote-cart'),
            )
        ));
    }

    /**
     * Render Add to Quote Button.
     */
    private function button_text()
    {
        $label = get_option('lqc_button_text', '');
        return in_array($label, array('', 'Cotizar ahora', 'Agregar a Cotización'), true)
            ? 'Añadir a cotización' : $label;
    }

    public function render_button($product_id = 0)
    {
        global $product;
        if (!$product_id && $product) {
            $product_id = $product->get_id();
        }

        if (!$product_id)
            return;

        $label = $this->button_text();

        echo '<button type="button" class="lqc-add-to-quote button alt" data-product_id="' . esc_attr($product_id) . '">';
        echo '<span class="lqc-action-label">'.esc_html($label).'</span>';
        echo '</button>';
    }

    public function render_button_shortcode($atts)
    {
        $product_id = 0;
        
        if (isset($atts['id']) && absint($atts['id']) > 0) {
            $product_id = absint($atts['id']);
        } else {
            $product_id = get_the_ID();
        }

        if (!$product_id) {
            return '';
        }

        $product_instance = wc_get_product($product_id);
        if (!$product_instance) {
            return '';
        }

        $button_text = $this->button_text();

        ob_start();
        ?>
        <div class="lqc-shortcode-container" style="margin-bottom: 20px;">
            <?php if ($product_instance->is_type('variable')): ?>
                <div class="lqc-shortcode-variations" style="margin-bottom: 15px;">
                    <?php 
                    $attributes = $product_instance->get_variation_attributes();
                    foreach ($attributes as $attribute_name => $options):
                    ?>
                        <div class="lqc-shortcode-variation-field" style="margin-bottom: 10px;">
                            <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px; color:#555;">
                                <?php echo wc_attribute_label($attribute_name); ?>:
                            </label>
                            <select class="lqc-shortcode-variation-select" data-attribute_name="attribute_<?php echo esc_attr(sanitize_title($attribute_name)); ?>" style="width: 100%; max-width: 300px; padding: 10px; border: 1px solid #d1d1d1; border-radius: 4px; background-color: #f7f7f7; color: #333; font-size: 14px; box-shadow: none; outline: none;">
                                <option value=""><?php esc_html_e('Elegir', 'woocommerce'); ?></option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>">
                                        <?php echo esc_html(apply_filters('woocommerce_variation_option_name', $option, null, $attribute_name, $product_instance)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <button type="button" class="button lqc-add-to-quote lqc-is-shortcode"
                data-product_id="<?php echo esc_attr($product_id); ?>">
                <span class="lqc-action-label"><?php echo esc_html($button_text); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function add_button_loop()
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            echo '<a class="button lqc-choose" href="'.esc_url($product->get_permalink()).'">Elegir opciones</a>';
            return;
        }
        $this->render_button();
    }

    public function add_button_single()
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            echo $this->render_button_shortcode(array('id' => $product->get_id()));
            return;
        }
        $this->render_button();
    }

    public function shortcode_button($atts)
    {
        global $product;
        $id = !empty($atts['id']) ? absint($atts['id']) : ($product ? $product->get_id() : get_the_ID());
        return $this->render_button_shortcode(array('id' => $id));
    }

    /**
     * Shortcode for Basket Icon/Counter.
     */
    public function shortcode_basket()
    {
        $count = Luma_Quote_Cart::get_instance()->cart->get_cart_count();
        ob_start();
        ?>
        <button type="button" aria-label="Ver mi cotización" class="lqc-basket-shortcode <?php echo get_option('lqc_enable_popup', 'yes') === 'yes' ? 'lqc-toggle-modal' : 'lqc-go-to-page'; ?>">
            <span class="dashicons dashicons-cart"></span>
            <span class="lqc-count">
                <?php echo esc_html($count); ?>
            </span>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Popup Modal in Footer.
     */
    public function render_popup()
    {
        if ('yes' !== get_option('lqc_enable_popup', 'yes')) {
            // If popup disabled, but mini basket enabled, we still might show the sticky badge but it links to page?
            // For simplicity if popup disabled, we don't render the modal HTML.
            // But creating the sticky badge here if enabled.
        }

        $show_sticky = get_option('lqc_enable_mini_basket', 'yes') === 'yes';
        $count = Luma_Quote_Cart::get_instance()->cart->get_cart_count();

        // If both popup and sticky are disabled, nothing to inject unless shortcode used.
        // However, we need the modal HTML if the popup is enabled, regardless of sticky button.
        $popup_enabled = get_option('lqc_enable_popup', 'yes') === 'yes';

        echo '<span class="lqc-announcement" role="status" aria-live="polite" aria-atomic="true"></span>';
        if ($show_sticky) {
            ?>
            <button type="button" id="lqc-floating-basket" class="<?php echo $popup_enabled ? 'lqc-toggle-modal' : 'lqc-go-to-page'; ?>">
                <span class="lqc-document-mark" aria-hidden="true"></span><span>Mi cotización</span>
                <span class="lqc-count">
                    <?php echo esc_html($count); ?>
                </span>
            </button>
            <?php
        }

        if ($popup_enabled) {
            ?>
            <div id="lqc-modal-overlay"></div>
            <div id="lqc-modal" role="dialog" aria-modal="true" aria-labelledby="lqc-modal-heading">
                <div class="lqc-modal-header">
                    <h3 id="lqc-modal-heading">
                        <?php esc_html_e('Mi cotización', 'luma-quote-cart'); ?>
                    </h3>
                    <button type="button" aria-label="Cerrar cotización" class="lqc-close-modal"><span aria-hidden="true">×</span></button>
                </div>
                <div class="lqc-modal-body">
                    <?php echo $this->get_cart_html(); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Shortcode for full Quote Page.
     */
    public function shortcode_cart()
    {
        return '<div class="lqc-cart-wrapper">' . $this->get_cart_html() . '</div>';
    }

    /**
     * Get HTML for cart table and form.
     */
    public function get_cart_html()
    {
        $cart = Luma_Quote_Cart::get_instance()->cart->get_cart_details();
        ob_start();

        if (empty($cart)) {
            echo '<p class="lqc-empty">' . esc_html__('Tu lista de cotización está vacía.', 'luma-quote-cart') . '</p>';
        } else {
            ?>
            <ol class="lqc-steps" aria-label="Pasos de la cotización">
                <li aria-current="step"><span>1</span> Productos</li>
                <li><span>2</span> Datos del proyecto</li>
            </ol>
            <div class="lqc-items-list">
                <?php if (LQC_Quote_PDF::enabled()): ?>
                    <p class="lqc-note">Revisa las cantidades. Verás los precios al generar tu PDF.</p>
                <?php endif; ?>
                <?php foreach ($cart as $item): ?>
                    <div class="lqc-item" data-id="<?php echo esc_attr($item['cart_key']); ?>">
                        <div class="lqc-item-image">
                            <?php echo $item['image']; // Already produces an <img> tag ?>
                        </div>
                        <div class="lqc-item-info">
                            <strong>
                                <a href="<?php echo esc_url($item['permalink']); ?>" target="_blank" style="color: #333; text-decoration: none;">
                                    <?php echo esc_html($item['name']); ?>
                                </a>
                            </strong>
                            <?php if ($item['sku'])
                                echo '<br><small style="color: #777; font-size: 11px;">SKU: ' . esc_html($item['sku']) . '</small>'; ?>
                            
                            <?php 
                            $parent_product = wc_get_product($item['product_id']);
                            if ($parent_product && $parent_product->is_type('variable')): 
                                $attributes = $parent_product->get_variation_attributes();
                                foreach ($attributes as $attribute_name => $options):
                                    $selected_val = isset($item['raw_variations']['attribute_' . sanitize_title($attribute_name)]) ? $item['raw_variations']['attribute_' . sanitize_title($attribute_name)] : '';
                                    ?>
                                    <div class="lqc-variation-field" style="margin-top: 5px;">
                                        <label style="font-size: 11px; display: block; color: #555; font-weight: bold;"><?php echo wc_attribute_label($attribute_name); ?>:</label>
                                        <select class="lqc-change-variation" data-attribute_name="attribute_<?php echo esc_attr(sanitize_title($attribute_name)); ?>" style="font-size: 12px; padding: 4px; width: 100%; max-width: 150px; border: 1px solid #d1d1d1; border-radius: 4px; background-color: #f7f7f7; color: #333; box-shadow: none; outline: none;">
                                            <option value=""><?php esc_html_e('Elegir', 'woocommerce'); ?></option>
                                            <?php foreach ($options as $option): ?>
                                                <option value="<?php echo esc_attr($option); ?>" <?php selected($selected_val, $option); ?>>
                                                    <?php echo esc_html(apply_filters('woocommerce_variation_option_name', $option, null, $attribute_name, $parent_product)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (!empty($item['variations'])): ?>
                                <br><small class="lqc-variations" style="color: #666; font-size: 11px;"><?php echo esc_html(implode(' | ', $item['variations'])); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="lqc-item-actions">
                            <label>Cantidad (<?php echo esc_html($item['unit']); ?>) <input type="number" min="<?php echo $item['unit'] === 'm²' ? '0.01' : '1'; ?>" step="<?php echo $item['unit'] === 'm²' ? '0.01' : '1'; ?>" class="lqc-qty" value="<?php echo esc_attr($item['quantity']); ?>"></label>
                            <button type="button" class="lqc-remove" aria-label="Quitar <?php echo esc_attr($item['name']); ?>">Quitar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button lqc-continue">Continuar con mis datos</button>
            <form class="lqc-contact-form" hidden>
                <button type="button" class="lqc-back">Volver a mis productos</button>
                <?php if (LQC_Quote_PDF::enabled()): ?>
                    <p class="lqc-note">Usaremos tu correo para enviarte una copia de la cotización.</p>
                <?php endif; ?>
                <!-- Configurable Fields -->
                <?php
                $fields = array(
                    'name' => array('label' => get_option('lqc_label_name', 'Nombre'), 'required' => true, 'type' => 'text'),
                    'company' => array('label' => get_option('lqc_label_company', 'Empresa'), 'required' => false, 'type' => 'text'),
                    'email' => array('label' => get_option('lqc_label_email', 'Correo electrónico'), 'required' => false, 'type' => 'email'),
                    'phone' => array('label' => get_option('lqc_label_phone', 'Teléfono / WhatsApp'), 'required' => true, 'type' => 'tel'),
                    'message' => array('label' => get_option('lqc_label_message', 'Mensaje'), 'required' => false, 'type' => 'textarea'),
                );

                if (LQC_Quote_PDF::enabled()) {
                    $fields['email']['required'] = true;
                    $fields['phone']['required'] = false;
                }
                foreach ($fields as $key => $field) {
                    $field_id = 'lqc_' . $key;
                    $is_enabled = get_option('lqc_field_enable_' . $key, 'yes') === 'yes';

                    if (!$is_enabled && !$field['required'])
                        continue;

                    $label = get_option('lqc_label_' . $key, $field['label']);
                    $req = $field['required'] ? 'required' : '';
                    $star = $field['required'] ? ' *' : '';

                    echo '<div class="lqc-form-group lqc-field-' . esc_attr($key) . '">';
                    echo '<label>' . esc_html($label . $star);

                    if ($field['type'] === 'textarea') {
                        echo '<textarea name="' . esc_attr($field_id) . '" ' . $req . '></textarea>';
                    } else {
                        echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($field_id) . '" ' . $req . '>';
                    }
                    echo '</label></div>';
                }
                ?>

                <div class="lqc-form-actions">
                    <button type="submit"
                        class="button primary"><?php echo LQC_Quote_PDF::enabled() ? 'Generar mi cotización' : 'Enviar solicitud'; ?></button>
                </div>
                <div class="lqc-response" role="status"></div>
            </form>
            <?php
        }

        return ob_get_clean();
    }
}
