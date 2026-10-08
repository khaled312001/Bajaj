@extends('layouts.app')
@section('title', 'استيراد وتصدير Excel')
@section('heading', 'استيراد وتصدير Excel')
@section('sub', 'نماذج ثابتة: حمّل، عبّي، وارفع مرة أخرى')

@section('content')
<div class="alert alert-info"><i class="fa-solid fa-circle-info"></i><div>
    <b>كيف تعمل المنظومة؟</b> 1) حمّل النموذج الثابت. 2) عبّئه (القوائم المنسدلة جاهزة). 3) ارفعه هنا وراجع المعاينة. 4) أكّد الاستيراد.
    نفس الأعمدة تُستخدم في <b>التصدير</b>، فيمكنك تصدير العملاء، تعديلهم في Excel ثم رفعهم لتحديث البيانات.
</div></div>

<div class="grid g3 mb">
    @foreach($types as $key => $t)
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid {{ $t['icon'] }}"></i> {{ $t['title'] }}</h3></div>
        <div class="card-body">
            <div class="btn-group mb">
                <a class="btn btn-soft" href="{{ route('data.template', $key) }}"><i class="fa-solid fa-download"></i> تحميل النموذج</a>
                <a class="btn btn-ghost" href="{{ route('data.export.' . $key) }}"><i class="fa-solid fa-file-export" style="color:#16a34a"></i> تصدير البيانات الحالية</a>
            </div>
            <form method="POST" action="{{ route('data.upload') }}" enctype="multipart/form-data">
                @csrf <input type="hidden" name="type" value="{{ $key }}">
                <label class="drop" style="display:block" id="drop-{{ $key }}">
                    <i class="fa-solid fa-cloud-arrow-up"></i><b>اسحب الملف هنا أو اضغط للاختيار</b>
                    <div class="small muted file-name">xlsx / xls / csv — حتى 10 ميجابايت</div>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required style="display:none">
                </label>
                @if($key === 'customers')
                    <div class="field mt"><label>إذا كان العميل موجوداً (نفس الكود أو الهاتف)</label>
                        <select class="input" name="mode"><option value="skip">تخطيه ولا تغيّر بياناته</option><option value="update">تحديث بياناته من الملف</option></select></div>
                @endif
                <button class="btn btn-primary btn-block mt" type="submit"><i class="fa-solid fa-magnifying-glass-chart"></i> رفع ومعاينة</button>
            </form>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> آخر عمليات الاستيراد</h3></div>
    <div class="table-wrap"><table class="tbl" data-filterable>
        <thead><tr><th>التاريخ</th><th data-filter="النوع">النوع</th><th>الملف</th><th data-filter="بواسطة">بواسطة</th><th>الصفوف</th><th>جديد</th><th>محدّث</th><th>فشل</th><th data-filter="الحالة">الحالة</th></tr></thead>
        <tbody>@forelse($imports as $i)
            <tr><td class="small">{{ $i->created_at->format('Y/m/d H:i') }}</td><td>{{ $types[$i->type]['title'] ?? $i->type }}</td><td>{{ $i->filename }}</td><td>{{ $i->user?->name }}</td>
                <td>{{ $i->total }}</td><td>{{ $i->created }}</td><td>{{ $i->updated }}</td><td>{{ $i->failed }}</td>
                <td>@if($i->status === 'done')<a class="badge green" href="{{ route('data.result', $i) }}">تم</a>@elseif($i->status === 'pending')<a class="badge amber" href="{{ route('data.preview', $i) }}">بانتظار التأكيد</a>@else<span class="badge gray">ملغي</span>@endif</td></tr>
        @empty<tr><td colspan="9"><div class="empty" style="padding:30px">لم يتم أي استيراد بعد</div></td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.drop').forEach(z => {
  const inp = z.querySelector('input[type=file]'), nm = z.querySelector('.file-name');
  inp.addEventListener('change', () => { if (inp.files[0]) nm.textContent = inp.files[0].name; });
  ['dragover', 'dragenter'].forEach(e => z.addEventListener(e, ev => { ev.preventDefault(); z.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(e => z.addEventListener(e, ev => { ev.preventDefault(); z.classList.remove('over'); }));
  z.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { inp.files = ev.dataTransfer.files; nm.textContent = inp.files[0].name; } });
});
</script>
@endpush
