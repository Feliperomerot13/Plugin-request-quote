<?php
if (!defined('ABSPATH')) {
    exit;
}

class Luma_Quote_Cart_Settings
{

    private $option_group = 'luma_quote_cart_settings';

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', function () {
            global $post;
            LQC_Quote_PDF::fields($post->ID);
        });
        add_action('woocommerce_variation_options_pricing', function ($index, $data, $variation) {
            LQC_Quote_PDF::fields($variation->ID, '['.$index.']');
        }, 10, 3);
        add_action('woocommerce_admin_process_product_object', array('LQC_Quote_PDF', 'save_fields'));
        add_action('woocommerce_admin_process_variation_object', array('LQC_Quote_PDF', 'save_fields'), 10, 2);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            esc_html__('Quote Settings', 'luma-quote-cart'),
            esc_html__('Request a Quote', 'luma-quote-cart'),
            'manage_woocommerce',
            'luma-quote-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting($this->option_group, 'lqc_recipient_emails');
        register_setting($this->option_group, 'lqc_button_text', array('default' => 'Agregar a Cotización'));
        register_setting($this->option_group, 'lqc_replace_add_to_cart');
        register_setting($this->option_group, 'lqc_enable_mini_basket', array('default' => 'yes'));
        register_setting($this->option_group, 'lqc_enable_popup', array('default' => 'yes'));
        register_setting($this->option_group, 'lqc_quote_page_id');
        register_setting($this->option_group, 'lqc_success_message', array('default' => '¡Solicitud enviada con éxito!'));

        add_settings_section(
            'lqc_general_section',
            esc_html__('Configuración General', 'luma-quote-cart'),
            null,
            'luma-quote-settings'
        );

        register_setting($this->option_group, 'lqc_enable_pdf', array('default' => 'no'));
        add_settings_field('lqc_enable_pdf', 'Emitir cotización PDF', array($this, 'render_checkbox_field'),
            'luma-quote-settings', 'lqc_general_section', array('id' => 'lqc_enable_pdf',
            'desc' => 'Usa las tarifas privadas de cada producto o variación. Los productos sin tarifa quedan en revisión.'));
        add_settings_field(
            'lqc_recipient_emails',
            esc_html__('Email(s) Destinatario', 'luma-quote-cart'),
            array($this, 'render_text_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_recipient_emails', 'desc' => 'Emails separados por coma para recibir las cotizaciones.')
        );

        add_settings_field(
            'lqc_button_text',
            esc_html__('Texto del Botón', 'luma-quote-cart'),
            array($this, 'render_text_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_button_text', 'default' => 'Agregar a Cotización')
        );

        add_settings_field(
            'lqc_replace_add_to_cart',
            esc_html__('Reemplazar "Añadir al Carrito"', 'luma-quote-cart'),
            array($this, 'render_checkbox_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_replace_add_to_cart', 'desc' => 'Ocultar el botón nativo de añadir al carrito.')
        );

        add_settings_field(
            'lqc_enable_mini_basket',
            esc_html__('Activar Mini Basket Flotante', 'luma-quote-cart'),
            array($this, 'render_checkbox_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_enable_mini_basket', 'default' => 'yes')
        );

        add_settings_field(
            'lqc_enable_popup',
            esc_html__('Activar Popup Modal', 'luma-quote-cart'),
            array($this, 'render_checkbox_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_enable_popup', 'default' => 'yes', 'desc' => 'Si se desactiva, redirige a la página de cotización.')
        );

        add_settings_field(
            'lqc_quote_page_id',
            esc_html__('Página de Cotización', 'luma-quote-cart'),
            array($this, 'render_page_selector'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_quote_page_id')
        );

        add_settings_field(
            'lqc_success_message',
            esc_html__('Mensaje de Éxito', 'luma-quote-cart'),
            array($this, 'render_text_field'),
            'luma-quote-settings',
            'lqc_general_section',
            array('id' => 'lqc_success_message')
        );

        add_settings_section(
            'lqc_email_section',
            esc_html__('Configuración de Correos (Diseño y Textos)', 'luma-quote-cart'),
            null,
            'luma-quote-settings'
        );

        add_settings_field(
            'lqc_email_logo',
            esc_html__('Logo para el Correo (URL)', 'luma-quote-cart'),
            array($this, 'render_text_field'),
            'luma-quote-settings',
            'lqc_email_section',
            array('id' => 'lqc_email_logo', 'desc' => 'Ingresa la URL directa del logo (ej. https://tudominio.com/logo.png).')
        );

        add_settings_field(
            'lqc_customer_email_subject',
            esc_html__('Asunto del Correo', 'luma-quote-cart'),
            array($this, 'render_text_field'),
            'luma-quote-settings',
            'lqc_email_section',
            array('id' => 'lqc_customer_email_subject', 'default' => 'Hemos recibido tu solicitud de cotización')
        );

        add_settings_field(
            'lqc_customer_email_header',
            esc_html__('Encabezado / Mensaje Principal', 'luma-quote-cart'),
            array($this, 'render_textarea_field'),
            'luma-quote-settings',
            'lqc_email_section',
            array('id' => 'lqc_customer_email_header', 'default' => 'Gracias por tu interés. Hemos recibido tu solicitud y pronto nos pondremos en contacto contigo con los detalles.')
        );

        register_setting($this->option_group, 'lqc_pdf_cover', array('sanitize_callback' => 'esc_url_raw'));
        add_settings_field('lqc_pdf_cover', 'Fotografía de la cotización PDF', array($this, 'render_text_field'),
            'luma-quote-settings', 'lqc_general_section', array('id' => 'lqc_pdf_cover',
            'desc' => 'URL de una fotografía panorámica JPEG o PNG de la biblioteca de medios. Es opcional.'));
        register_setting($this->option_group, 'lqc_email_logo');
        register_setting($this->option_group, 'lqc_customer_email_subject', array('default' => 'Hemos recibido tu solicitud de cotización'));
        register_setting($this->option_group, 'lqc_customer_email_header', array('default' => 'Gracias por tu interés. Hemos recibido tu solicitud y pronto nos pondremos en contacto contigo con los detalles.'));
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Configuración Luma Quote Cart', 'luma-quote-cart'); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('luma-quote-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_text_field($args)
    {
        $value = get_option($args['id'], isset($args['default']) ? $args['default'] : '');
        echo '<input type="text" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="regular-text">';
        if (isset($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function render_checkbox_field($args)
    {
        $value = get_option($args['id'], isset($args['default']) ? $args['default'] : 'no');
        echo '<input type="checkbox" name="' . esc_attr($args['id']) . '" value="yes" ' . checked($value, 'yes', false) . '>';
        if (isset($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function render_page_selector($args)
    {
        $value = get_option($args['id']);
        wp_dropdown_pages(array(
            'name' => $args['id'],
            'selected' => $value,
            'show_option_none' => __('Seleccionar página', 'luma-quote-cart'),
        ));
        echo '<p class="description">' . esc_html__('Página que contiene el shortcode [luma_quote_cart]. Usada si el Popup está desactivado.', 'luma-quote-cart') . '</p>';
    }

    public function render_textarea_field($args)
    {
        $value = get_option($args['id'], isset($args['default']) ? $args['default'] : '');
        echo '<textarea name="' . esc_attr($args['id']) . '" class="large-text" rows="3">' . esc_textarea($value) . '</textarea>';
        if (isset($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }
}
