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
        $bank = trim((string) Settings::get('bank_account', ''));
        $bankNo = preg_match('/\d[\d\- ]{4,}\d/', $bank, $m) ? trim($m[0]) : '';
        $bankName = preg_replace('/^[\s\-–:()،,]+|[\s\-–:()،,]+$/u', '', str_replace($bankNo, '', $bank));
        // "010… / 010…" renders scrambled in RTL; keep the numbers and separate them with a plain dash
        $phones = preg_split('/\s*[\/,،|\-]\s*|\s{2,}/u', trim((string) Settings::get('company_phone', '')), -1, PREG_SPLIT_NO_EMPTY);

        return [
            'name' => Settings::get('company_legal_name', 'جنوب الصعيد لوسائل النقل الخفيف'),
            'name_en' => Settings::get('company_name_en', 'South Upper Egypt Light Transport'),
            'brand' => 'بجاج قنا',
            'phone' => implode(' - ', $phones),
            'address' => Settings::get('company_address', ''),
            'register' => Settings::get('commercial_register', ''),
            'tax' => Settings::get('tax_card', ''),
            'bank' => $bank,
            'bank_text' => $bankNo ? 'رقم ' . $bankNo . ($bankName ? ' - ' . $bankName : '') : $bank,
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

        // 'paper': printed on the company's pre-printed ZARAD letterhead sheet -> keep clear of its top band,
        // bottom band and the logo column on the left edge (margins are swapped by mPDF in RTL: left = physical right)
        $paper = ($letterhead['paper'] ?? false) === true;
        $companyDoc = ($letterhead['company'] ?? false) === true;
        $margins = match (true) {
            $paper => ['l' => 16, 'r' => 33, 't' => 47, 'b' => 41],
            $companyDoc => ['l' => 14, 'r' => 14, 't' => 12, 'b' => 26],
            (bool) $letterhead => ['l' => 18, 'r' => 18, 't' => 36, 'b' => 34],
            default => ['l' => 14, 'r' => 14, 't' => 14, 'b' => 18],
        };
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $landscape ? 'A4-L' : 'A4',
            'margin_left' => $margins['l'], 'margin_right' => $margins['r'],
            'margin_top' => $margins['t'], 'margin_bottom' => $margins['b'],
            'margin_header' => 0, 'margin_footer' => $letterhead && ! $companyDoc ? 0 : 7,
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
        if ($paper) {
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        }
        if ($companyDoc) {
            $c = self::company();
            $mpdf->SetHTMLFooter('<table width="100%" style="font-size:9pt;font-weight:bold;color:#2e3192;border-top:0.5mm solid #2e3192"><tr>'
                . '<td width="58%" style="padding-top:1.5mm">' . ($c['address'] ? 'العنوان : ' . e($c['address']) : '') . ($c['phone'] ? '<br>الهاتف : ' . e($c['phone']) : '') . '</td>'
                . '<td style="padding-top:1.5mm">' . ($c['register'] ? 'رقم السجل التجاري : ' . e($c['register']) : '') . ($c['tax'] ? '<br>رقم البطاقة الضريبية : ' . e($c['tax']) : '') . '</td>'
                . '<td width="12%" align="left" style="font-size:7.5pt;color:#94a3b8;font-weight:normal">{PAGENO} / {nbpg}</td></tr></table>');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        }
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
