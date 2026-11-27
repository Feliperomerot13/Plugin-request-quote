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
        $fonts = dirname(__DIR__).'/assets/fonts/';
        $this->AddFont('Inter', '', 'InterRegular.json', $fonts);
        $this->AddFont('Inter', 'B', 'InterSemiBold.json', $fonts);
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
        $this->SetFont('Inter', $style, $size);
        $this->SetXY($x, $y);
        $this->Cell($width, 6, $this->letters($text), 0, 0, $align);
    }

    private function rule($y)
    {
        $this->SetDrawColor(215, 214, 206);
        $this->Line(16, $y, 194, $y);
    }

    private function picture($file, $x, $y, $width, $height, $cover = false)
    {
        if (!$file || !is_file($file)) {
            return false;
        }
        $info = @getimagesize($file);
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true)) {
            return false;
        }
        $ratio = $cover ? max($width / $info[0], $height / $info[1])
                        : min($width / $info[0], $height / $info[1]);
        $iw = $ratio * $info[0];
        $ih = $ratio * $info[1];
        if ($cover) {
            $this->_out(sprintf('q %.3F %.3F %.3F %.3F re W n', $x*$this->k, ($this->h-$y-$height)*$this->k,
                $width*$this->k, $height*$this->k));
        }
        try {
            $this->Image($file, $x + ($width-$iw)/2, $y + ($height-$ih)/2,
                $iw, $ih, $info[2] === IMAGETYPE_JPEG ? 'JPEG' : 'PNG');
        } catch (Exception $e) {
            return false;
        } finally {
            if ($cover) { $this->_out('Q'); }
        }
        return true;
    }

    public function Header()
    {
        $this->SetTextColor(45, 46, 40);
        if (!$this->picture($this->quote['logo'], 15, 7, 35, 12)) {
            $this->label(15, 8, 'LUMA', 20, 'B');
            $this->label(15, 15, 'A R Q U I T E C T U R A', 6.5);
        }
        $this->label(106, 7, $this->quote['folio'], 9.5, 'B', 89, 'R');
        $this->label(106, 13, $this->quote['date'], 8.5, '', 89, 'R');
        if ($this->PageNo() > 1) { $this->rule(24); }
    }

    public function Footer()
    {
        $this->rule(281);
        $this->SetTextColor(86, 86, 77);
        $this->label(15, 283, 'Luma Arquitectura', 8, 'B');
        $this->label(56, 283, 'lumaa.co', 8);
        $this->label(151, 283, $this->PageNo().'  /  {nb}', 8, '', 44, 'R');
    }

    private function money($value)
    {
        return '$ '.number_format($value, 0, ',', '.');
    }

    private function display_text($x, $y, $text, $points, $width, $alignment = 'L')
    {
        $this->SetXY($x, $y);
        $this->SetFont('Inter', '', $points);
        $this->Cell($width, $points * 0.38, $this->letters($text), 0, 0, $alignment);
    }

    private function paragraph($x, $y, $width, $text, $size, $leading, $style = '')
    {
        $this->SetFont('Inter', $style, $size);
        $this->SetXY($x, $y);
        $this->MultiCell($width, $leading, $this->letters($text), 0, 'L');
        return $this->GetY();
    }

    private function table_head($y)
    {
        $this->SetTextColor(87, 87, 77);
        $this->label(15, $y, 'PRODUCTO', 7.5, 'B');
        $this->label(97, $y, 'CANTIDAD', 7.5, 'B', 23, 'R');
        $this->label(120, $y, 'UNITARIO', 7.5, 'B', 35, 'R');
        $this->label(155, $y, 'IMPORTE', 7.5, 'B', 40, 'R');
        $this->rule($y+8);
        return $y+8;
    }

    public function render()
    {
        $q = $this->quote;
        $this->AddPage();
        $hero = $this->picture($q['cover'] ?? '', 0, 25, 210, 62, true);
        $title_y = $hero ? 77 : 31;
        // The title begins on a paper-colored inset at the foot of the photograph.
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, $title_y, 98, 25, 'F');
        $this->SetTextColor(38, 42, 37);
        $this->display_text(14, $title_y+2, 'Cotización', 37, 82);
        $y = $title_y+12;
        $this->SetTextColor(103, 98, 85);
        $this->label(108, $y, 'PREPARADA PARA', 7.4, 'B');
        $this->SetTextColor(38, 42, 37);
        $y = $this->paragraph(108, $y+6, 87, $q['client'], 11.2, 5, 'B');
        if ($q['project'] !== '') { $y = $this->paragraph(108, $y+1, 87, $q['project'], 9, 4.4); }
        $y = max($y+6, $title_y+29);
        $this->display_text(15, $y, 'Selección de productos', 17, 100);
        $this->label(112, $y+1, 'Pesos colombianos  /  Valores antes de IVA', 8, '', 83, 'R');
        $y = $this->table_head($y+11);
        $net = 0; $tax = 0; $missing = 0;
        foreach ($q['items'] as $line) {
            if ($y > 240) {
                $this->AddPage();
                $y = $this->table_head(30);
            }
            $this->picture($line['image'], 16, $y+2, 29, 19);
            $this->SetTextColor(38, 42, 37);
            $bottom = $this->paragraph(49, $y+6, 49, $line['name'], 11, 4.6, 'B');
            $this->SetTextColor(87, 87, 77);
            if ($line['detail'] !== '') {
                $bottom = $this->paragraph(49, $bottom+1, 49, $line['detail'], 8.6, 4.1);
            }
            $bottom = max($y+24, $bottom+4);
            $this->SetTextColor(38, 42, 37);
            $quantity = str_replace('.', ',', (string) $line['qty']);
            $this->label(97, $y+8, $quantity.' '.$line['unit'], 9.6, '', 23, 'R');
            if ($line['net'] === null) {
                $missing++;
                $this->label(122, $y+8, 'En revisión', 9, '', 73, 'R');
            } else {
                $base = round($line['qty']*$line['net']);
                $net += $base;
                $tax += round($line['qty']*$line['tax']);
                $this->label(120, $y+8, $this->money($line['net']), 9.8, '', 35, 'R');
                $this->label(155, $y+8, $this->money($base), 10.2, 'B', 40, 'R');
            }
            $this->rule($bottom);
            $y = $bottom;
        }
        if ($y > 220) { $this->AddPage(); $y = 33; }
        $y += 8;
        $this->SetTextColor(87, 87, 77);
        $this->label(15, $y, 'CONDICIONES', 7.5, 'B');
        $notes = $missing ? 'Las partidas en revisión quedan pendientes de valoración. ' : '';
        $this->paragraph(15, $y+8, 79, $notes.$q['terms'], 9.1, 4.6);

        if ($missing < count($q['items'])) {
            $this->SetFillColor(243, 241, 234);
            $this->Rect(101, $y, 94, 46, 'F');
            $this->SetTextColor(66, 68, 60);
            $this->label(105,$y+4,'Subtotal sin IVA',9);
            $this->label(148,$y+4,$this->money($net),9.5,'',42,'R');
            $this->label(105,$y+11,'IVA de productos',9);
            $this->label(148,$y+11,$this->money($tax),9.5,'',42,'R');
            $this->SetDrawColor(198, 193, 174);
            $this->Line(106, $y+21, 189, $y+21);
            $this->SetTextColor(38, 42, 37);
            $this->label(105, $y+23, $missing ? 'PARTIDAS VALORADAS CON IVA' : 'TOTAL DE PRODUCTOS CON IVA', 7.6, 'B');
            $this->display_text(105,$y+29,$this->money($net+$tax),24,85,'R');
        }
        return $this->Output('S');
    }
}
