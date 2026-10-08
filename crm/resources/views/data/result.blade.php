@extends('layouts.app')
@section('title', 'نتيجة الاستيراد')
@section('heading', 'نتيجة الاستيراد — ' . $meta['title'])
@section('sub', $import->filename)

@section('content')
<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><div>اكتمل الاستيراد.</div></div>
<div class="grid g4 mb">
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-plus"></i></div><div><div class="lbl">أُضيف</div><div class="val">{{ $import->created }}</div></div></div>
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-pen"></i></div><div><div class="lbl">تم تحديثه</div><div class="val">{{ $import->updated }}</div></div></div>
    <div class="card stat"><div class="ico red"><i class="fa-solid fa-xmark"></i></div><div><div class="lbl">فشل</div><div class="val">{{ $import->failed }}</div></div></div>
    <div class="card stat"><div class="ico amber"><i class="fa-solid fa-forward"></i></div><div><div class="lbl">إجمالي الصفوف</div><div class="val">{{ $import->total }}</div></div></div>
</div>
@if(!empty($import->errors))
    <div class="card mb"><div class="card-head"><h3>الصفوف التي لم تُستورد</h3><a class="btn btn-sm btn-ghost" href="{{ route('data.errors', $import) }}"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> تقرير الأخطاء</a></div>
        <div class="table-wrap" style="max-height:340px;overflow-y:auto"><table class="tbl"><tbody>@foreach(array_slice($import->errors, 0, 100) as $e)<tr><td style="width:100px"><span class="badge red">صف {{ $e['row'] }}</span></td><td>{{ $e['message'] }}</td></tr>@endforeach</tbody></table></div></div>
@endif
<div class="btn-group">
    <a class="btn btn-primary" href="{{ route('customers.index') }}"><i class="fa-solid fa-users"></i> عرض العملاء</a>
    <a class="btn btn-ghost" href="{{ route('data.index') }}">استيراد ملف آخر</a>
</div>
@endsection
