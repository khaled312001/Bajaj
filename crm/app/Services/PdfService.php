<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/** Professional A4 Arabic PDFs (mPDF) with company logo header, serial and page numbers. */
class PdfService
{
    public static function company(): array
    {
        return [
            'name' => Settings::get('company_legal_name', 'جنوب الصعيد لوسائل النقل الخفيف'),
            'brand' => Settings::get('offer_title') ? 'بجاج قنا' : 'بجاج قنا',
            'phone' => Settings::get('company_phone', ''),
            'address' => Settings::get('company_address', ''),
            'register' => Settings::get('commercial_register', ''),
            'tax' => Settings::get('tax_card', ''),
            'bank' => Settings::get('bank_account', ''),
            'manager' => Settings::get('manager_name', ''),
            'hours' => Settings::get('working_hours', ''),
        ];
    }

    /** Render a Blade view (inside pdf.layout) to a PDF string. */
    public static function render(string $view, array $data, string $title, bool $landscape = false, ?string $footerNote = null, ?array $letterhead = null): string
    {
        $defaults = (new ConfigVariables())->getDefaults();
        $fonts = (new FontVariables())->getDefaults();
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $landscape ? 'A4-L' : 'A4',
            'margin_left' => $letterhead ? 18 : 14, 'margin_right' => $letterhead ? 18 : 14,
            'margin_top' => $letterhead ? 36 : 14, 'margin_bottom' => $letterhead ? 34 : 18,
            'margin_header' => 0, 'margin_footer' => $letterhead ? 0 : 7,
            'tempDir' => $tmp,
            'fontDir' => array_merge($defaults['fontDir'], [storage_path('fonts')]),
            'fontdata' => $fonts['fontdata'] + [
                'tajawal' => ['R' => 'Tajawal-Regular.ttf', 'B' => 'Tajawal-Bold.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'tajawal', 'default_font_size' => 11,
            'autoScriptToLang' => true, 'autoLangToFont' => false,
        ]);
        $mpdf->SetDirectionality('rtl');
        if (count($data['r']['rows'] ?? []) > 250) {
            $mpdf->simpleTables = true; // thousands of cells: skip collapsed-border calculations
        }
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor((string) Settings::get('app_name', 'Bajaj CRM'));
        $mpdf->SetCreator('Bajaj CRM');

        $html = View::make($view, $data + ['company' => self::company(), 'title' => $title, 'logo' => public_path('img/logo.jpg')])->render();
        if ($letterhead) {
            // pre-printed company letterhead (image header + footer), replaces the standard header / footer
            $mpdf->SetHTMLHeader('<div style="position:absolute;left:0;top:0"><img src="' . $letterhead['header'] . '" style="width:210mm;height:28mm"></div>');
            $mpdf->SetHTMLFooter('<div style="position:absolute;left:0;bottom:0"><img src="' . $letterhead['footer'] . '" style="width:210mm;height:26.4mm"></div>');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        }
        $mpdf->SetHTMLFooter('<table width="100%" style="font-size:8pt;color:#64748b;border-top:0.4mm solid #cbd5e1"><tr>'
            . '<td width="40%" align="right">' . e($footerNote ?? $title) . '</td>'
            . '<td width="30%" align="center">صفحة {PAGENO} من {nbpg}</td>'
            . '<td width="30%" align="left" dir="ltr">' . now()->format('Y/m/d H:i') . '</td></tr></table>');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public static function response(string $pdf, string $filename, bool $inline = true)
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($filename),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
