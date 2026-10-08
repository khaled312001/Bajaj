@extends('pdf.layout')
@section('body')
@php
    $money = $r['money'] ?? [];
    $cards = $r['cards'] ?? [];
@endphp
<h1 class="title">{{ $r['title'] }}</h1>
<div class="title-line">الفترة: {{ $from->format('Y/m/d') }} — {{ $to->format('Y/m/d') }}@if($employee) | الموظف: {{ $employee }}@endif</div>
@if($cards)
<table class="cards"><tr>
@foreach($cards as $label => $value)
    <td><div class="cl">{{ $label }}</div><div class="cv">{{ is_numeric($value) ? number_format($value, $value == (int) $value ? 0 : 2) : $value }}</div></td>
    @if($loop->iteration % 4 === 0 && ! $loop->last)</tr><tr>@endif
@endforeach
</tr></table>
@endif
@php($chunks = array_chunk($r['rows'], 60))
@forelse($chunks as $ci => $chunk)
<table class="data" repeat_header="1">
    <thead><tr>@foreach($r['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($chunk as $row)
        <tr class="{{ $loop->even ? 'alt' : '' }}">@foreach($row as $i => $cell)<td class="n">{{ in_array($i + 1, $money, true) && is_numeric($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
@empty
<table class="data"><tr><td class="n">لا توجد بيانات في هذه الفترة</td></tr></table>
@endforelse
@endsection
