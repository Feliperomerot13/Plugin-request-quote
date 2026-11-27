<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/vendor/lqc-fpdf/fpdf.php';

/** Renders a commercial snapshot. No database lookups or remote image requests. */
final class LQC_Document extends \LumaQuotePdf\FPDF
{
    private $quote;

    public function __construct(array $quote)
    {
        parent::__construct();
        $this->quote = $quote;
        $this->SetMargins(16, 16, 16);
        $this->SetAutoPageBreak(false);
        $this->SetTitle('Cotización · Luma Arquitectura', true);
        $this->SetAuthor('Luma Arquitectura');
        $this->AliasNbPages();
    }

    private function letters($text)
    {
        return function_exists('iconv')
            ? (string) iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $text)
            : strtr((string) $text, array('á'=>chr(225),'é'=>chr(233),'í'=>chr(237),'ó'=>chr(243),'ú'=>chr(250),'ñ'=>chr(241),'²'=>chr(178),'·'=>chr(183)));
    }

    private function label($x, $y, $text, $size = 10, $style = '', $width = 0, $align = 'L')
    {
        $this->SetFont('Helvetica', $style, $size);
        $this->SetXY($x, $y);
        $this->Cell($width, 6, $this->letters($text), 0, 0, $align);
    }

    private function rule($y)
    {
        $this->SetDrawColor(215, 214, 206);
        $this->Line(16, $y, 194, $y);
    }

    private function picture($file, $x, $y, $width, $height)
    {
        if (!$file || !is_file($file)) {
            return false;
        }
        $info = @getimagesize($file);
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true)) {
            return false;
        }
        $ratio = min($width / $info[0], $height / $info[1]);
        $iw = $ratio * $info[0];
        $ih = $ratio * $info[1];
        try {
            $this->Image($file, $x + ($width-$iw)/2, $y + ($height-$ih)/2,
                $iw, $ih, $info[2] === IMAGETYPE_JPEG ? 'JPEG' : 'PNG');
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function Header()
    {
        $this->SetTextColor(34, 36, 34);
        if (!$this->picture($this->quote['logo'], 16, 11, 39, 12)) {
            $this->label(16, 11, 'LUMA', 21, 'B');
            $this->label(16, 19, 'A R Q U I T E C T U R A', 7);
        }
        $this->label(100, 11, $this->quote['folio'], 10, 'B', 94, 'R');
        $this->label(100, 18, $this->quote['date'], 9, '', 94, 'R');
        $this->rule(29);
    }

    public function Footer()
    {
        $this->rule(281);
        $this->SetTextColor(81, 83, 79);
        $this->label(16, 284, 'Luma Arquitectura   /   lumaa.co', 8);
        $this->label(143, 284, $this->PageNo().' / {nb}', 8, '', 51, 'R');
    }

    private function money($value)
    {
        return '$ '.number_format($value, 0, ',', '.');
    }

    private function table_head($y)
    {
        $this->SetFillColor(239, 238, 232);
        $this->Rect(16, $y, 178, 10, 'F');
        $this->SetTextColor(52, 54, 49);
        $this->label(19, $y+2, 'Producto', 9, 'B');
        $this->label(99, $y+2, 'Cantidad', 9, 'B', 22, 'R');
        $this->label(121, $y+2, 'Unitario', 9, 'B', 35, 'R');
        $this->label(156, $y+2, 'Importe', 9, 'B', 35, 'R');
        return $y+10;
    }

    public function render()
    {
        $q = $this->quote;
        $this->AddPage();
        $this->SetXY(15, 35);
        $this->SetFont('Times', '', 38);
        $this->Cell(158, 17, $this->letters('Cotización'));
        $this->SetFillColor(228, 178, 50);
        $this->Rect(16, 55, 15, 1.4, 'F');
        $this->label(16, 61, 'PREPARADA PARA', 7.8, 'B');
        $this->SetXY(16, 69);
        $this->SetFont('Helvetica', 'B', 13);
        $this->MultiCell(175, 6, $this->letters($q['client']));
        $this->SetFont('Helvetica', '', 10);
        $this->MultiCell(175, 5, $this->letters($q['project']));
        $y = $this->GetY()+7;
        $this->label(16, $y, 'Selección de productos', 14, 'B');
        $this->label(118, $y, 'COP   /   Valores antes de IVA', 8.5, '', 76, 'R');
        $y = $this->table_head($y+9);
        $net = 0; $tax = 0; $missing = 0;
        foreach ($q['items'] as $line) {
            if ($y > 230) {
                $this->AddPage();
                $y = $this->table_head(37);
            }
            $this->picture($line['image'], 17, $y+2, 25, 20);
            $this->SetTextColor(34, 36, 34);
            $this->SetXY(45, $y+4);
            $this->SetFont('Helvetica', 'B', 11);
            $this->MultiCell(54, 4.7, $this->letters($line['name']));
            $this->SetXY(45, $this->GetY()+1);
            $this->SetFont('Helvetica', '', 8.3);
            $this->MultiCell(54, 4.1, $this->letters($line['detail']));
            $bottom = max($y+25, $this->GetY()+3);
            $this->label(98, $y+8, $line['qty'].' '.$line['unit'], 9.5, '', 23, 'R');
            if ($line['net'] === null) {
                $missing++;
                $this->label(122, $y+8, 'En revisión', 9, '', 69, 'R');
            } else {
                $base = round($line['qty']*$line['net']);
                $net += $base;
                $tax += round($line['qty']*$line['tax']);
                $this->label(121, $y+8, $this->money($line['net']), 9.5, '', 35, 'R');
                $this->label(156, $y+8, $this->money($base), 10, 'B', 35, 'R');
            }
            $this->rule($bottom);
            $y = $bottom;
        }
        if ($y > 211) { $this->AddPage(); $y = 37; }
        $y += 5;
        if ($missing < count($q['items'])) {
            $this->label(98,$y,'Subtotal sin IVA',9.5);
            $this->label(150,$y,$this->money($net),10,'',42,'R');
            $this->label(98,$y+7,'IVA de productos',9.5);
            $this->label(150,$y+7,$this->money($tax),10,'',42,'R');
            $y+=17;
            $this->SetFillColor(38,42,40);
            $this->Rect(16,$y,178,17,'F');
            $this->SetTextColor(255,255,255);
            $this->label(20,$y+5,$missing ? 'Partidas valoradas con IVA' : 'Total de productos con IVA',12,'B');
            $this->label(131,$y+5,$this->money($net+$tax),16,'B',59,'R');
            $y+=22;
        }
        $this->SetTextColor(34,36,34);
        $this->label(16,$y,'Alcance de la cotización',10,'B');
        $this->SetXY(16,$y+7);
        $this->SetFont('Helvetica','',9.2);
        if ($missing) {
            $this->MultiCell(176,4.6,$this->letters('Las partidas en revisión requieren confirmación comercial y se presentan sin importe.'));
        }
        $this->MultiCell(176,4.6,$this->letters($q['terms']));
        return $this->Output('S');
    }
}
