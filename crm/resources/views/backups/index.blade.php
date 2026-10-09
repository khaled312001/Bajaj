@extends('layouts.app')
@section('title', 'النسخ الاحتياطي')
@section('heading', 'النسخ الاحتياطي')
@section('sub', 'نسخة يومية تلقائية لقاعدة البيانات')

@section('content')
<div class="grid g3 mb">
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-shield-heart"></i></div><div><div class="lbl">آخر نسخة</div><div class="val" style="font-size:16px">{{ $last ? \Illuminate\Support\Carbon::parse($last)->format('Y/m/d H:i') : 'لا توجد' }}</div></div></div>
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-database"></i></div><div><div class="lbl">عدد النسخ المحفوظة</div><div class="val">{{ $backups->total() }}</div></div></div>
    <div class="card stat"><div class="ico purple"><i class="fa-solid fa-hard-drive"></i></div><div><div class="lbl">المساحة المستخدمة</div><div class="val" style="font-size:18px">{{ number_format($totalSize / 1048576, 2) }} MB</div></div></div>
</div>

<div class="grid g2 mb">
    <form method="POST" action="{{ route('backups.settings') }}" class="card"><div class="card-head"><h3><i class="fa-solid fa-gear"></i> إعدادات النسخ</h3></div>
        @csrf<div class="card-body">
            <label class="check mb"><input type="checkbox" name="auto" value="1" @checked($auto)> نسخة تلقائية يومية</label>
            <div class="field mb"><label>الاحتفاظ بالنسخ (يوم)</label><input class="input" type="number" name="retention" min="1" max="365" value="{{ $retention }}"></div>
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ</button>
            <div class="hint mt">تُنشأ النسخة التلقائية مرة يومياً عبر المجدول (cron)، أو عند أول دخول للمدير بعد منتصف الليل إن لم يتوفر cron.</div>
        </div></form>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bolt"></i> نسخة الآن</h3></div>
        <div class="card-body"><p class="muted">ينشئ ملف SQL مضغوط (gzip) بكل الجداول: العملاء والصفقات والأقساط والمتابعات والمستخدمين والإعدادات.</p>
            <form method="POST" action="{{ route('backups.run') }}">@csrf<button class="btn btn-green"><i class="fa-solid fa-cloud-arrow-down"></i> إنشاء نسخة احتياطية الآن</button></form>
            <div class="alert alert-warning mt"><i class="fa-solid fa-triangle-exclamation"></i><div>الملفات تحتوي بيانات العملاء كاملة. احتفظ بها في مكان آمن ولا تشاركها. يمكنك استرجاع أي نسخة من الجدول أسفل الصفحة مباشرة من المتصفح.</div></div></div></div>
</div>

<div class="modal" id="m-restore">
    <div class="modal-box">
        <div class="modal-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> استرجاع نسخة احتياطية</h3><button class="modal-x" data-close="m-restore"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST" action="">@csrf
            <div class="modal-body">
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><div><b>تحذير:</b> سيتم استبدال كل بيانات النظام الحالية ببيانات هذه النسخة (<span data-label="date"></span>). سيأخذ النظام نسخة احتياطية من الحالة الحالية أولاً تلقائياً، لكن أي تغييرات حدثت بعد تاريخ هذه النسخة وقبل الاسترجاع ستُفقد ما لم تُستعد لاحقاً من تلك النسخة الاحتياطية التلقائية.</div></div>
                <div class="field mt"><label>اكتب كلمة «استرجاع» للتأكيد</label><input class="input" type="text" name="confirm" required autocomplete="off"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-restore">إلغاء</button><button class="btn btn-danger"><i class="fa-solid fa-clock-rotate-left"></i> استرجاع البيانات الآن</button></div>
        </form>
    </div>
</div>

<div class="card"><div class="card-head"><h3><i class="fa-solid fa-list"></i> النسخ المحفوظة</h3></div>
    <div class="table-wrap"><table class="tbl" data-filterable><thead><tr><th>الملف</th><th data-filter="النوع">النوع</th><th>الحجم</th><th>الجداول / السجلات</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead><tbody>
    @forelse($backups as $b)
        <tr><td dir="ltr" style="text-align:right">{{ $b->filename }}</td><td>{{ $b->kind === 'auto' ? 'تلقائية' : 'يدوية' }}@if($b->creator) — {{ $b->creator->name }}@endif</td>
            <td class="num">{{ number_format($b->size / 1024, 1) }} KB</td><td class="num">{{ $b->tables }} / {{ number_format($b->rows) }}</td>
            <td>@if($b->status === 'ok')<span class="badge green">ناجحة</span>@else<span class="badge red" title="{{ $b->error }}">فشلت</span>@endif</td>
            <td class="muted small">{{ $b->created_at->format('Y/m/d H:i') }}</td>
            <td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                @if($b->status === 'ok' && is_file($b->path()))<a class="btn btn-xs btn-soft" href="{{ route('backups.download', $b) }}"><i class="fa-solid fa-download"></i> تنزيل</a>
                <button type="button" class="btn btn-xs btn-danger-soft" data-open="m-restore" data-action="{{ route('backups.restore', $b) }}" data-fill-date="{{ $b->created_at->format('Y/m/d H:i') }}"><i class="fa-solid fa-clock-rotate-left"></i> استرجاع</button>
                @elseif($b->status === 'ok')<span class="badge amber" title="الملف غير موجود على السيرفر">الملف مفقود</span>@endif
                <form method="POST" action="{{ route('backups.destroy', $b) }}" data-confirm="حذف هذه النسخة؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form></div></td></tr>
    @empty<tr><td colspan="7"><div class="empty">لا توجد نسخ بعد</div></td></tr>@endforelse
    </tbody></table></div><div class="card-pad">{{ $backups->links('pagination.rtl') }}</div></div>
@endsection
