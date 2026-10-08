@extends('layouts.app')
@section('title', 'معاينة الاستيراد')
@section('heading', 'معاينة الاستيراد — ' . $meta['title'])
@section('sub', $import->filename)

@section('content')
<div class="grid g4 mb">
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-list"></i></div><div><div class="lbl">إجمالي الصفوف</div><div class="val">{{ $import->total }}</div></div></div>
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-circle-check"></i></div><div><div class="lbl">صفوف سليمة</div><div class="val">{{ $import->valid }}</div></div></div>
    <div class="card stat"><div class="ico red"><i class="fa-solid fa-circle-xmark"></i></div><div><div class="lbl">صفوف بها أخطاء</div><div class="val">{{ $import->failed }}</div></div></div>
    <div class="card stat"><div class="ico amber"><i class="fa-solid fa-circle-question"></i></div><div><div class="lbl">أعمدة غير معروفة (تُتجاهل)</div><div class="val">{{ count($unknown) }}</div></div></div>
</div>

@if($unknown)<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div>الأعمدة التالية غير موجودة في النموذج الثابت وسيتم تجاهلها: <b>{{ implode('، ', $unknown) }}</b></div></div>@endif

@if($import->failed)
<div class="card mb">
    <div class="card-head"><h3><i class="fa-solid fa-bug" style="color:var(--red)"></i> أخطاء الصفوف (لن تُستورد)</h3>
        <a class="btn btn-sm btn-ghost" href="{{ route('data.errors', $import) }}"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> تحميل تقرير الأخطاء</a></div>
    <div class="table-wrap" style="max-height:340px;overflow-y:auto"><table class="tbl">
        <thead><tr><th style="width:120px">الصف في الملف</th><th>المشكلة</th></tr></thead>
        <tbody>@foreach(array_slice($import->errors ?? [], 0, 100) as $e)<tr><td><span class="badge red">{{ $e['row'] }}</span></td><td>{{ $e['message'] }}</td></tr>@endforeach</tbody>
    </table></div>
</div>
@endif

<div class="card">
    <form method="POST" action="{{ route('data.confirm', $import) }}" class="card-body">
        @csrf
        @if($import->type === 'customers')
            <div class="field mb" style="max-width:420px"><label>إذا كان العميل موجوداً بالفعل</label>
                <select class="input" name="mode"><option value="skip" @selected($mode === 'skip')>تخطيه ولا تغيّر بياناته</option><option value="update" @selected($mode === 'update')>تحديث بياناته من الملف</option></select></div>
        @endif
        @if($import->valid === 0)
            <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><div>لا توجد صفوف سليمة للاستيراد. صحّح الملف وأعد رفعه.</div></div>
        @else
            <p class="mb">سيتم استيراد <b>{{ $import->valid }}</b> صف سليم{{ $import->failed ? ' وتجاهل ' . $import->failed . ' صف به أخطاء' : '' }}. العملية مسجلة باسمك في سجل النشاط.</p>
        @endif
        <div class="btn-group">
            @if($import->valid)<button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> تأكيد الاستيراد</button>@endif
            <button class="btn btn-ghost" type="submit" formaction="{{ route('data.cancel', $import) }}" data-nolock>إلغاء</button>
        </div>
    </form>
</div>
@endsection
