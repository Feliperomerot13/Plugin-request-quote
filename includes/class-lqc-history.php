<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LQC_History
{
    const PAGE = 'luma-cotizaciones';
    const CAP = 'manage_woocommerce';

    public static function init()
    {
        add_action('admin_menu', function () {
            add_submenu_page('woocommerce', 'Cotizaciones Luma', 'Cotizaciones Luma', self::CAP, self::PAGE, array(__CLASS__, 'screen'));
        });
        add_action('admin_post_lqc_export_quotes', array(__CLASS__, 'export'));
    }

    private static function table()
    {
        global $wpdb;
        $table = $wpdb->prefix.'lqc_quotes';
        if (get_option('lqc_quotes_schema') !== '1') {
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            $collation = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                folio varchar(32) NOT NULL,
                created_gmt datetime NOT NULL,
                is_test tinyint(1) NOT NULL DEFAULT 0,
                mail_ok tinyint(1) NOT NULL DEFAULT 0,
                payload longtext NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY folio (folio),
                KEY cohort (is_test,id)
            ) $collation;");
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                throw new RuntimeException('No se pudo preparar el registro de cotizaciones.');
            }
            update_option('lqc_quotes_schema', '1', false);
        }
        return $table;
    }

    public static function folio($request_id)
    {
        $digest = hash_hmac('sha256', WC()->session->get_customer_id().'|'.$request_id, wp_salt('nonce'));
        return 'LUM-'.strtoupper(substr($digest, 0, 16));
    }

    public static function save($folio, $data, $items, $kind)
    {
        global $wpdb;
        $table = self::table();
        $lines = array();
        foreach ($items as $item) {
            $lines[] = trim($item['quantity'].' '.$item['unit'].' · '.$item['name']
                .(empty($item['variations']) ? '' : ' ('.implode(', ', $item['variations']).')'));
        }
        $data['references'] = $lines;
        $data['kind'] = $kind;
        $json = wp_json_encode($data);
        if ($json === false || !$items) {
            throw new RuntimeException('Revisa las referencias seleccionadas.');
        }
        // A unique folio also protects against simultaneous submissions.
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (folio, created_gmt, is_test, payload) VALUES (%s, %s, %d, %s)
            ON DUPLICATE KEY UPDATE id = id", $folio, current_time('mysql', true), !empty($data['lqc_test_copy']), $json
        ));
        if ($inserted === false) {
            throw new RuntimeException('No se pudo guardar la cotización.');
        }
        return $inserted === 1;
    }

    public static function mail_accepted($folio)
    {
        global $wpdb;
        $wpdb->update(self::table(), array('mail_ok'=>1), array('folio'=>$folio));
    }

    private static function values($row)
    {
        $data = json_decode($row->payload, true);
        return array(
            'Folio'=>$row->folio, 'Fecha local'=>get_date_from_gmt($row->created_gmt, 'Y-m-d H:i'),
            'Contacto'=>$data['lqc_name'], 'Correo'=>$data['lqc_email'], 'Teléfono'=>$data['lqc_phone'],
            'Empresa'=>$data['lqc_company'], 'Proyecto'=>$data['lqc_project'], 'Ubicación'=>$data['lqc_location'],
            'Cotizar envío'=>$data['lqc_shipping'] ? 'Sí' : 'No', 'Referencias'=>implode("\n", $data['references']),
            'Notas'=>$data['lqc_message'], 'Documento'=>$data['kind'],
            'Correos'=>$row->mail_ok ? 'Aceptados para envío' : 'Sin confirmar', 'Tipo'=>$row->is_test ? 'Prueba' : 'Comercial',
        );
    }

    private static function require_access()
    {
        if (!current_user_can(self::CAP)) {
            wp_die('No tienes acceso a las cotizaciones.', '', array('response'=>403));
        }
    }

    private static function view_url($test, $page = 1)
    {
        return add_query_arg(array('page'=>self::PAGE, 'pruebas'=>(int)$test, 'pagina'=>$page), admin_url('admin.php'));
    }

    private static function is_test()
    {
        return isset($_GET['pruebas']) && $_GET['pruebas'] === '1' ? 1 : 0;
    }

    public static function screen()
    {
        global $wpdb;
        self::require_access();
        $table = self::table();
        $test = self::is_test();
        $counts = array(0, 0);
        foreach ($wpdb->get_results("SELECT is_test, COUNT(*) AS total FROM $table GROUP BY is_test") as $group) {
            $counts[(int)$group->is_test] = (int)$group->total;
        }
        $pages = max(1, (int)ceil($counts[$test] / 25));
        $page = min($pages, max(1, absint($_GET['pagina'] ?? 1)));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE is_test=%d ORDER BY id DESC LIMIT 25 OFFSET %d", $test, ($page-1)*25));
        $csv = wp_nonce_url(add_query_arg(array('action'=>'lqc_export_quotes', 'pruebas'=>$test), admin_url('admin-post.php')), 'lqc_export_'.$test);
        ?>
        <div class="wrap">
            <h1>Cotizaciones Luma</h1>
            <p>Consulta los datos que dejó cada cliente y las referencias que solicitó. El historial comienza con la versión 1.3.</p>
            <nav class="nav-tab-wrapper" aria-label="Tipo de cotización">
            <?php foreach (array('Comerciales', 'Pruebas') as $type=>$label) : ?>
                <a class="nav-tab <?php echo $type === $test ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::view_url($type)); ?>"><?php echo esc_html($label.' ('.$counts[$type].')'); ?></a>
            <?php endforeach; ?>
            </nav>
            <p><a class="button" href="<?php echo esc_url($csv); ?>">Exportar <?php echo $test ? 'pruebas' : 'cotizaciones comerciales'; ?> en CSV</a></p>
            <table class="widefat striped"><thead><tr><th>Folio y fecha</th><th>Contacto</th><th>Proyecto</th><th>Detalle</th></tr></thead><tbody>
            <?php if (!$rows) : ?><tr><td colspan="4">Todavía no hay <?php echo $test ? 'pruebas registradas' : 'cotizaciones comerciales registradas'; ?>.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : $v = self::values($row); ?>
                <tr>
                    <td><strong><?php echo esc_html($v['Folio']); ?></strong><br><?php echo esc_html($v['Fecha local']); ?></td>
                    <td><?php echo esc_html($v['Contacto']); ?><br><a href="<?php echo esc_url('mailto:'.$v['Correo']); ?>"><?php echo esc_html($v['Correo']); ?></a><br><?php echo esc_html($v['Teléfono']); ?></td>
                    <td><?php echo esc_html($v['Proyecto']); ?><br><?php echo esc_html($v['Ubicación']); ?></td>
                    <td><details><summary style="cursor:pointer">Ver solicitud</summary>
                    <?php foreach (array('Empresa','Referencias','Cotizar envío','Notas','Documento','Correos') as $field) : if ($v[$field] === '') continue; ?>
                        <p><strong><?php echo esc_html($field); ?></strong><br><?php echo nl2br(esc_html($v[$field])); ?></p>
                    <?php endforeach; ?></details></td>
                </tr>
            <?php endforeach; ?></tbody></table>
            <?php if ($pages > 1) : ?><p>
                <?php if ($page > 1) : ?><a href="<?php echo esc_url(self::view_url($test, $page-1)); ?>">Anterior</a> · <?php endif; ?>
                <?php echo esc_html("Página $page de $pages"); ?>
                <?php if ($page < $pages) : ?> · <a href="<?php echo esc_url(self::view_url($test, $page+1)); ?>">Siguiente</a><?php endif; ?>
            </p><?php endif; ?>
        </div>
        <?php
    }

    public static function export()
    {
        global $wpdb;
        self::require_access();
        $test = self::is_test();
        check_admin_referer('lqc_export_'.$test);
        $table = self::table();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="luma-'.($test ? 'pruebas' : 'cotizaciones').'-'.wp_date('Y-m-d').'.csv"');
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array('Folio','Fecha local','Contacto','Correo','Teléfono','Empresa','Proyecto','Ubicación','Cotizar envío','Referencias','Notas','Documento','Correos','Tipo'), ';', '"', '');
        $cursor = (int)$wpdb->get_var("SELECT MAX(id) FROM $table") + 1;
        do {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE is_test=%d AND id<%d ORDER BY id DESC LIMIT 200", $test, $cursor));
            foreach ($rows as $row) {
                $values = self::values($row);
                foreach ($values as &$value) {
                    if (preg_match('/^(?:[\s\x{FEFF}]*[=+@-]|[\t\r\n])/u', $value)) {
                        $value = "'".$value;
                    }
                }
                unset($value);
                fputcsv($stream, $values, ';', '"', '');
                $cursor = (int)$row->id;
            }
        } while (count($rows) === 200);
        fclose($stream);
        exit;
    }
}
