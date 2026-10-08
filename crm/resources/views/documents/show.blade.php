@extends('layouts.app')
@section('title', $def['title'] ?? 'مستند')
@section('heading', ($def['title'] ?? 'مستند') . ' — ' . $docNo)
@section('sub', 'معاينة قبل الطباعة')

@section('content')
<div class="row wrap mb no-print">
    <a class="btn btn-ghost" href="{{ route('documents.index') }}"><i class="fa-solid fa-arrow-right"></i> المستندات</a>
    <a class="btn btn-primary" href="{{ route('documents.pdf', [$log, 'download' => 1]) }}"><i class="fa-solid fa-download"></i> تحميل PDF</a>
    <a class="btn btn-ghost" href="{{ route('documents.pdf', $log) }}" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> فتح للطباعة</a>
    @if(! $log->legacy_ref)<a class="btn btn-ghost" href="{{ route('documents.edit', $log) }}"><i class="fa-solid fa-pen"></i> تعديل وإعادة إصدار</a>@endif
    @if(auth()->user()->isAdmin())<form method="POST" action="{{ route('documents.destroy', $log) }}" data-confirm="حذف سجل المستند؟">@csrf @method('DELETE')<button class="btn btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form>@endif
</div>
<div class="card"><iframe src="{{ route('documents.pdf', $log) }}" title="معاينة PDF" style="width:100%;height:min(80vh,1100px);border:0;border-radius:12px;background:#e5e7eb"></iframe></div>
@endsection
