<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: tajawal; font-size: 11pt; color: #0f172a; direction: rtl; }
    .serif { font-family: tajawal; }
    table { border-collapse: collapse; }
    .hd { width: 100%; border-bottom: 0.8mm solid #1d4ed8; }
    .hd td { vertical-align: middle; padding: 0 0 3mm 0; }
    .logo { background: #000000; width: 26mm; padding: 1mm; text-align: center; }
    .co-name { font-size: 15pt; font-weight: bold; color: #0b1b3a; }
    .co-sub { font-size: 8.5pt; color: #475569; line-height: 1.6; }
    .doc-box { text-align: center; font-size: 9pt; color: #475569; }
    .doc-no { font-size: 11pt; font-weight: bold; color: #1d4ed8; direction: ltr; }
    .doc-no.boxed { display: inline-block; font-size: 14pt; color: #0b1b3a; border: 0.35mm solid #0b1b3a; padding: 1.5mm 5mm; direction: ltr; }
    h1.title { text-align: center; font-size: 19pt; color: #0b1b3a; margin: 6mm 0 1mm 0; }
    h1.title.doc-title { font-size: 30pt; margin: 8mm 0 2mm 0; }
    .title-line { text-align: center; color: #64748b; font-size: 9pt; margin-bottom: 5mm; }
    .data { width: 100%; }
    .data td, .data th { border: 0.25mm solid #cbd5e1; padding: 2mm 3mm; font-size: 10pt; }
    .data th { background: #eef2ff; color: #1e3a8a; font-weight: bold; text-align: center; }
    .data td.n { text-align: center; }
    .data tr.alt td { background: #f8fafc; }
    .kv { width: 100%; margin-bottom: 2mm; border-collapse: collapse; }
    .kv td { border: none; padding: 2.2mm 2mm; font-size: 13pt; font-weight: bold; color: #0f172a; }
    .kv td.k { width: 30%; }
    .cards { width: 100%; margin-bottom: 4mm; }
    .cards td { border: 0.3mm solid #c7d2fe; background: #f5f7ff; padding: 3mm; text-align: center; width: 25%; }
    .cards .cl { font-size: 8.5pt; color: #475569; }
    .cards .cv { font-size: 14pt; font-weight: bold; color: #1d4ed8; }
    .sign { width: 100%; margin-top: 14mm; }
    .sign td { text-align: center; font-weight: bold; padding-top: 2mm; font-size: 12pt; }
    .sign .line { height: 13mm; border-bottom: 0.3mm solid #475569; margin: 0 10mm 2mm 10mm; }
    .note { font-size: 9pt; color: #475569; margin-top: 3mm; }
    .text { line-height: 2.1; font-size: 13pt; font-weight: bold; text-align: justify; }
    .strong { font-weight: bold; }
    .box { border: 0.4mm solid #1d4ed8; padding: 3mm 4mm; background: #f8fbff; margin: 3mm 0; }
</style>
</head>
<body>
<table class="hd"><tr>
    <td style="width:30mm"><table><tr><td class="logo"><img src="{{ $logo }}" style="width:24mm"></td></tr></table></td>
    <td style="padding-right:4mm">
        <div class="co-name">{{ $company['name'] }}</div>
        <div class="co-sub">
            @if($company['address']){{ $company['address'] }}<br>@endif
            @if($company['phone'])هاتف: <span dir="ltr">{{ $company['phone'] }}</span>@endif
            @if($company['register']) &nbsp;|&nbsp; س.ت: {{ $company['register'] }}@endif
            @if($company['tax']) &nbsp;|&nbsp; بطاقة ضريبية: {{ $company['tax'] }}@endif
        </div>
    </td>
    <td class="doc-box" style="width:42mm">
        @isset($docNo)<div class="doc-no boxed">{{ $docNo }}</div>@endisset
        <div style="margin-top:1.5mm">التاريخ: {{ $docDate ?? now()->format('Y/m/d') }}</div>
    </td>
</tr></table>
@yield('body')
</body>
</html>
