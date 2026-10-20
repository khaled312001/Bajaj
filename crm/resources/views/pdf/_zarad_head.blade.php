{{-- the ZARAD auto letterhead of the company documents: the name faces the logo, the date under it --}}
<table style="width:100%"><tr>
    <td style="vertical-align:middle">
        <div style="font-size:15pt;font-weight:bold;color:#2e3192">{{ $company['name'] }}</div>
        <div style="font-size:9pt;color:#2e3192;direction:ltr;text-align:right">{{ $company['name_en'] }}</div>
        <div style="font-size:8.5pt;color:#334155;margin-top:1mm">@if($company['register'])سجل تجاري : {{ $company['register'] }}@endif @if($company['tax']) &nbsp;|&nbsp; بطاقة ضريبية : {{ $company['tax'] }}@endif</div>
    </td>
    <td style="width:58mm;text-align:left;vertical-align:middle">
        <img src="{{ public_path('img/zarad-logo.png') }}" style="width:50mm">
    </td>
</tr></table>
<div style="text-align:left;font-size:11pt;font-weight:bold;color:#334155;margin-top:{{ 3 * $gap }}mm">التاريخ: {{ $docDate ?? now()->format('Y/m/d') }}</div>
