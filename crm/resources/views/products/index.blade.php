@extends('layouts.app')
@section('title', 'المنتجات والأسعار')
@section('heading', 'المنتجات والأسعار')
@section('sub', $products->count() . ' منتج')

@section('content')
<div class="card mb">
    <div class="card-head"><h3><i class="fa-solid fa-plus"></i> إضافة منتج</h3></div>
    <form method="POST" action="{{ route('products.store') }}" class="card-body">
        @csrf
        <div class="form-grid">
            <div class="field span-2"><label>اسم المنتج <span class="req">*</span></label><input class="input" name="name" required></div>
            <div class="field"><label>السعر الكاش (ج.م) <span class="req">*</span></label><input class="input" type="number" step="0.01" min="0" name="price" required></div>
            <div class="field"><label>الضمان</label><input class="input" name="warranty" placeholder="ضمان 24 شهر أو 20,000 كم"></div>
            <div class="field span-2"><label>المطلوب من العميل / الضامن</label><input class="input" name="requirements"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-plus"></i> إضافة</button></div>
    </form>
</div>

<div class="card">
    <div class="table-wrap"><table class="tbl" data-filterable>
        <thead><tr><th>المنتج</th><th>السعر</th><th>الضمان</th><th>المطلوب</th><th data-filter="الحالة">الحالة</th><th></th></tr></thead>
        <tbody>@foreach($products as $p)
            @php($fid = 'pf' . $p->id)
            <tr style="{{ $p->is_active ? '' : 'opacity:.55' }}">
                <td><input form="{{ $fid }}" class="input" name="name" value="{{ $p->name }}" style="min-width:230px" required></td>
                <td><input form="{{ $fid }}" class="input" type="number" step="0.01" min="0" name="price" value="{{ $p->price }}" style="width:130px" required></td>
                <td><input form="{{ $fid }}" class="input" name="warranty" value="{{ $p->warranty }}" style="min-width:170px"></td>
                <td><input form="{{ $fid }}" class="input" name="requirements" value="{{ $p->requirements }}" style="min-width:230px"></td>
                <td><input form="{{ $fid }}" type="hidden" name="is_active" value="0"><label class="check"><input form="{{ $fid }}" type="checkbox" name="is_active" value="1" @checked($p->is_active)> نشط</label></td>
                <td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                    <form id="{{ $fid }}" method="POST" action="{{ route('products.update', $p) }}">@csrf @method('PUT')<button class="btn btn-xs btn-primary" title="حفظ"><i class="fa-solid fa-floppy-disk"></i></button></form>
                    <form method="POST" action="{{ route('products.destroy', $p) }}" data-confirm="حذف المنتج {{ $p->name }}؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form>
                </div></td>
            </tr>
        @endforeach</tbody>
    </table></div>
</div>
@endsection
