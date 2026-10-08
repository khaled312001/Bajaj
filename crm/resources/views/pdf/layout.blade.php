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
    .doc-box { text-align: left; font-size: 9pt; color: #475569; }
    .doc-no { font-size: 11pt; font-weight: bold; color: #1d4ed8; direction: ltr; }
    h1.title { text-align: center; font-size: 19pt; color: #0b1b3a; margin: 6mm 0 1mm 0; }
    .title-line { text-align: center; color: #64748b; font-size: 9pt; margin-bottom: 5mm; }
    .data { width: 100%; }
    .data td, .data th { border: 0.25mm solid #cbd5e1; padding: 2mm 3mm; font-size: 10pt; }
    .data th { background: #eef2ff; color: #1e3a8a; font-weight: bold; text-align: center; }
    .data td.n { text-align: center; }
    .data tr.alt td { background: #f8fafc; }
    .kv { width: 100%; margin-bottom: 4mm; }
    .kv td { border: 0.25mm solid #e2e8f0; padding: 2mm 3mm; font-size: 10pt; }
    .kv td.k { background: #f1f5f9; color: #475569; width: 28%; font-weight: bold; }
    .cards { width: 100%; margin-bottom: 4mm; }
    .cards td { border: 0.3mm solid #c7d2fe; background: #f5f7ff; padding: 3mm; text-align: center; width: 25%; }
    .cards .cl { font-size: 8.5pt; color: #475569; }
    .cards .cv { font-size: 14pt; font-weight: bold; color: #1d4ed8; }
    .sign { width: 100%; margin-top: 14mm; }
    .sign td { text-align: center; font-weight: bold; padding-top: 2mm; font-size: 10pt; }
    .sign .line { height: 13mm; border-bottom: 0.3mm solid #475569; margin: 0 10mm 2mm 10mm; }
    .note { font-size: 9pt; color: #475569; margin-top: 3mm; }
    .text { line-height: 2.1; font-size: 12pt; text-align: justify; }
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
        @isset($docNo)<div class="doc-no">{{ $docNo }}</div>@endisset
        <div>التاريخ: {{ $docDate ?? now()->format('Y/m/d') }}</div>
    </td>
</tr></table>
@yield('body')
</body>
</html>
