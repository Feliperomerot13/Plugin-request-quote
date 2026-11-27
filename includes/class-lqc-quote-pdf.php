<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LQC_Quote_PDF
{
    public static function enabled()
    {
        return get_option('lqc_enable_pdf', 'no') === 'yes';
    }

    public static function fields($product_id, $suffix = '')
    {
        foreach (array('net' => 'Precio privado sin IVA (COP)', 'tax' => 'IVA unitario (COP)') as $key => $label) {
            $meta = '_lqc_'.$key;
            woocommerce_wp_text_input(array(
                'id' => $meta.'_'.$product_id, 'name' => ($suffix ? $meta.'_variation' : $meta).$suffix, 'label' => $label,
                'type' => 'number', 'value' => get_post_meta($product_id, $meta, true),
                'custom_attributes' => array('min' => '0', 'step' => '1'),
            ));
        }
        woocommerce_wp_select(array(
            'id' => '_lqc_unit_'.$product_id, 'name' => ($suffix ? '_lqc_unit_variation' : '_lqc_unit').$suffix,
            'label' => 'Unidad de cotización', 'value' => get_post_meta($product_id, '_lqc_unit', true),
            'options' => array('' => 'Según tarifario', 'piece' => 'Pieza', 'm2' => 'Metro cuadrado'),
        ));
    }

    public static function save_fields($product, $index = null)
    {
        foreach (array('_lqc_net', '_lqc_tax', '_lqc_unit') as $field) {
            $value = $index === null ? ($_POST[$field] ?? null) : ($_POST[$field.'_variation'][$index] ?? null);
            if (!is_string($value)) { continue; }
            $value = trim(wp_unslash($value));
            if ($field === '_lqc_unit') {
                if ($value === '') { $product->delete_meta_data($field); continue; }
                if (!in_array($value, array('piece','m2'), true)) { continue; }
            } elseif ($value === '') {
                $product->delete_meta_data($field);
                continue;
            } elseif (!ctype_digit($value) || strlen($value) > 10) {
                WC_Admin_Meta_Boxes::add_error('Usa pesos enteros positivos en la tarifa comercial.');
                continue;
            }
            $product->update_meta_data($field, $value);
        }
    }

    private static function artwork($id, $size = 'medium')
    {
        if (!$id) { return ''; }
        $image = image_get_intermediate_size($id, $size);
        $original = get_attached_file($id);
        if (!$original) { return ''; }
        return !empty($image['file']) ? dirname($original).'/'.basename($image['file']) : $original;
    }

    public static function issue($data, $items, $request_id, $mailer)
    {
        if (!is_email($data['lqc_email']) || !wp_is_uuid($request_id, 4)) {
            throw new RuntimeException('Verifica tu correo y los productos seleccionados.');
        }
        $session = WC()->session->get_customer_id();
        $key = 'lqc_doc_'.hash_hmac('sha256', $session.'|'.$request_id, wp_salt('nonce'));
        $saved = get_transient($key);
        if (is_array($saved)) { return $saved; }
        if (!$items) { throw new RuntimeException('Agrega un producto a la lista.'); }
        $lock = $key.'_busy';
        if (!add_option($lock, time(), '', false)) {
            if ((int) get_option($lock) < time()-300) { delete_option($lock); }
            throw new RuntimeException('La cotización está en proceso. Vuelve a intentarlo en un momento.');
        }
        try {
            $saved = get_transient($key);
            if (is_array($saved)) { return $saved; }
            $folio = 'LUM-'.strtoupper(substr(str_replace('-', '', wp_generate_uuid4()), 0, 12));
            $lines = array();
            foreach ($items as $item) {
                $product = wc_get_product($item['variation_id'] ?: $item['product_id']);
                if (!$product) { throw new RuntimeException('Una referencia dejó de estar disponible. Revisa tu selección.'); }
                list($net, $tax) = LQC_Tariff::price($product, $item);
                $priced = !$product->is_type('variable') && $net !== null;
                $lines[] = array(
                    'name' => $product->get_name(), 'detail' => implode(' · ', $item['variations']),
                    'image' => self::artwork($product->get_image_id()), 'qty' => $item['quantity'],
                    'unit' => LQC_Tariff::unit($product) === 'm2' ? 'm²' : 'ud.',
                    'net' => $priced ? (int)$net : null, 'tax' => $priced ? (int)$tax : null,
                );
            }
            require_once __DIR__.'/class-lqc-document.php';
            $logo_id = attachment_url_to_postid((string) get_option('lqc_email_logo'));
            $document = new LQC_Document(array(
                'cover' => self::artwork(attachment_url_to_postid((string) get_option('lqc_pdf_cover')), 'large'),
                'logo' => self::artwork($logo_id ?: get_theme_mod('custom_logo')),
                'folio' => $folio, 'date' => wp_date('d/m/Y'), 'items' => $lines,
                'client' => $data['lqc_company'] ?: $data['lqc_name'],
                'project' => $data['lqc_company'] ? $data['lqc_name'] : '',
                'terms' => 'Transporte, instalación, disponibilidad y vigencia sujetos a confirmación del equipo comercial.',
            ));
            $bytes = $document->render();
            $result = array('folio' => $folio, 'pdf' => base64_encode($bytes),
                'message' => 'Cotización generada. Puedes descargar tu PDF.');
            // Keep the generated document available on retry, including when mail fails.
            set_transient($key, $result, DAY_IN_SECONDS);
            Luma_Quote_Cart::get_instance()->cart->clear_cart();
            $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.wp_generate_uuid4();
            $file = $directory.'/'.$folio.'.pdf';
            if (!wp_is_writable(dirname($directory)) || !mkdir($directory, 0700)) {
                $result['message'] .= ' El envío por correo quedó pendiente.';
            } else {
                try {
                    if (file_put_contents($file, $bytes) !== strlen($bytes) || !$mailer->send_quote($data, array($file), $items)) {
                        $result['message'] .= ' No se confirmó el envío a ambos correos.';
                    } else {
                        $result['message'] .= ' Enviamos una copia a tu correo y al equipo de Luma.';
                    }
                } catch (Throwable $e) {
                    $result['message'] .= ' El envío por correo quedó pendiente.';
                } finally {
                    if (is_file($file)) { unlink($file); }
                    rmdir($directory);
                }
            }
            set_transient($key, $result, DAY_IN_SECONDS);
            return $result;
        } finally {
            delete_option($lock);
        }
    }
}
