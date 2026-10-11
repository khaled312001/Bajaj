<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: {{ \App\Support\TrafficLayout::font() }}; font-weight: bold; color: #000; direction: rtl; }
    div.t { font-family: {{ \App\Support\TrafficLayout::font() }}; position: absolute; margin: 0; padding: 0; line-height: 1; white-space: nowrap; font-weight: bold; }
</style>
</head>
<body>
{{-- every line sits at the exact position of the traffic-approved original (see App\Support\TrafficLayout) --}}
@foreach(\App\Support\TrafficLayout::items($type, $d, $docDate ?? null) as [$top, $x, $w, $align, $size, $text, $ltr])
<div class="t" style="top:{{ $top }}mm;left:{{ $x }}mm;width:{{ $w }}mm;text-align:{{ $align }};font-size:{{ $size }}pt;direction:{{ $ltr ? 'ltr' : 'rtl' }}">@if($ltr)<bdo dir="ltr">{{ $text }}</bdo>@else{{ $text }}@endif</div>
@endforeach
</body>
</html>
