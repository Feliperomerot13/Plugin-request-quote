<?php
if (!defined('ABSPATH')) {
    exit;
}

class Luma_Quote_Cart_Mailer
{

    public function send_quote($data, $attachments = array(), $items = null)
    {
        $admin_emails = get_option('lqc_recipient_emails');
        if (empty($admin_emails)) {
            $admin_emails = get_option('admin_email');
        }

        $customer_name = $data['lqc_name'];
        $customer_email = $data['lqc_email'];
        $customer_phone = $data['lqc_phone'];
        $message_note = $data['lqc_message'];

        $cart_details = $items ?? Luma_Quote_Cart::get_instance()->cart->get_cart_details();

        // 1. Send to Admin
        $admin_subject = sprintf(__('Nueva Solicitud de Cotización de %s', 'luma-quote-cart'), $customer_name);
        $admin_headers = array('Content-Type: text/html; charset=UTF-8');
        if (!empty($customer_email) && is_email($customer_email)) {
            $admin_headers[] = 'Reply-To: ' . $customer_name . ' <' . $customer_email . '>';
        }

        $admin_content = $this->get_email_content($data, $cart_details, 'admin');
        $admin_sent = wp_mail($admin_emails, $admin_subject, $admin_content, $admin_headers, $attachments);

        // 2. Send to Customer (if email provided)
        $customer_sent = true;
        if (!empty($customer_email) && is_email($customer_email)) {
            $customer_subject = get_option('lqc_customer_email_subject', 'Hemos recibido tu solicitud de cotización');
            $customer_headers = array('Content-Type: text/html; charset=UTF-8');

            $customer_content = $this->get_email_content($data, $cart_details, 'customer');
            $customer_sent = wp_mail($customer_email, $customer_subject, $customer_content, $customer_headers, $attachments);
        }

        return $admin_sent && $customer_sent;
    }

    private function get_email_content($data, $cart_details, $type = 'admin')
    {
        $customer_name = $data['lqc_name'];
        $customer_company = isset($data['lqc_company']) ? $data['lqc_company'] : '';
        $customer_email = $data['lqc_email'];
        $customer_phone = $data['lqc_phone'];
        $message_note = $data['lqc_message'];
        $logo_url = get_option('lqc_email_logo');

        ob_start();
        ?>
        <html>

        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }

                th,
                td {
                    padding: 10px;
                    border: 1px solid #ddd;
                    text-align: left;
                }

                th {
                    background: #f4f4f4;
                }

                h2 {
                    color: #555;
                }

                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    color: #999;
                }
            </style>
        </head>

        <body>
            <?php if (!empty($logo_url)): ?>
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" style="max-height: 80px;" />
                </div>
            <?php endif; ?>

            <?php if ($type === 'admin'): ?>
                <h2><?php esc_html_e('Nueva Solicitud de Cotización', 'luma-quote-cart'); ?></h2>
                <div>
                    <?php echo wp_kses_post(nl2br(get_option('lqc_customer_email_header', 'Gracias por tu interés.'))); ?>
                </div>
                <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
                <p><strong><?php esc_html_e('Nombre:', 'luma-quote-cart'); ?></strong> <?php echo esc_html($customer_name); ?></p>
                <?php if (!empty($customer_company)): ?>
                    <p><strong><?php esc_html_e('Empresa:', 'luma-quote-cart'); ?></strong> <?php echo esc_html($customer_company); ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Teléfono/WhatsApp:', 'luma-quote-cart'); ?></strong>
                    <?php echo esc_html($customer_phone); ?></p>
                <?php if (!empty($customer_email)): ?>
                    <p><strong><?php esc_html_e('Email:', 'luma-quote-cart'); ?></strong> <?php echo esc_html($customer_email); ?></p>
                <?php endif; ?>
            <?php else: // Customer ?>
                <h2><?php echo esc_html(get_option('lqc_customer_email_subject', 'Hemos recibido tu solicitud')); ?></h2>
                <div>
                    <?php echo wp_kses_post(nl2br(get_option('lqc_customer_email_header', 'Gracias por tu interés.'))); ?>
                </div>

                <p><strong><?php esc_html_e('Tus datos:', 'luma-quote-cart'); ?></strong><br>
                    <?php echo esc_html($customer_name); ?><br>
                    <?php if (!empty($customer_company)): ?>
                        <?php echo esc_html($customer_company); ?><br>
                    <?php endif; ?>
                    <?php echo esc_html($customer_phone); ?><br><?php echo esc_html($customer_email); ?>
                </p>
            <?php endif; ?>

            <p><strong>Proyecto</strong> · <?php echo esc_html($data['lqc_project']); ?><br>
                <strong>Ubicación</strong> · <?php echo esc_html($data['lqc_location']); ?><br>
                <strong>Envío</strong> · <?php echo $data['lqc_shipping'] ? 'Solicitado, pendiente de cotizar' : 'No solicitado'; ?></p>
            <?php if ($message_note !== ''): ?>
                <p><strong>Notas para la cotización</strong><br><?php echo nl2br(esc_html($message_note)); ?></p>
            <?php endif; ?>

            <h3><?php esc_html_e('Productos Solicitados', 'luma-quote-cart'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Imagen', 'luma-quote-cart'); ?></th>
                        <th><?php esc_html_e('Producto', 'luma-quote-cart'); ?></th>
                        <th><?php esc_html_e('SKU', 'luma-quote-cart'); ?></th>
                        <th><?php esc_html_e('Cantidad', 'luma-quote-cart'); ?></th>
                        <th><?php esc_html_e('Enlace', 'luma-quote-cart'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_details as $item): ?>
                        <tr>
                            <td style="width: 60px; text-align: center;">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo esc_url($item['image_url']); ?>" alt="Img" style="width:50px; height:auto; border-radius:4px;" />
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($item['name']); ?></strong>
                                <?php if (!empty($item['variations'])): ?>
                                    <br><small style="color: #666;"><?php echo esc_html(implode(' | ', $item['variations'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($item['sku']); ?></td>
                            <td><?php echo esc_html($item['quantity']); ?></td>
                            <td><a
                                    href="<?php echo esc_url($item['permalink']); ?>"><?php esc_html_e('Ver Producto', 'luma-quote-cart'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer">
                <p><?php printf(__('Enviado vía Luma Quote Cart desde %s', 'luma-quote-cart'), get_bloginfo('name')); ?></p>
            </div>
        </body>

        </html>
        <?php
        return ob_get_clean();
    }
}
