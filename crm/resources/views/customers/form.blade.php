@extends('layouts.app')
@php($editing = $customer->exists)
@section('title', $editing ? 'تعديل عميل' : 'عميل جديد')
@section('heading', $editing ? 'تعديل بيانات العميل' : 'تسجيل عميل جديد')

@section('content')
@php($isAdmin = auth()->user()->isAdmin())
<form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="card">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card-body">
        <div id="dup-box" class="pill-alert mb" style="display:none" data-url="{{ route('customers.check-phone') }}" data-self="{{ $customer->code }}">
            <i class="fa-solid fa-triangle-exclamation"></i><span></span><a class="btn btn-xs btn-danger" style="margin-right:auto" href="#">فتح الملف</a>
        </div>

        <div class="form-grid">
            <div class="form-section"><i class="fa-solid fa-id-card"></i> البيانات الأساسية</div>
            <div class="field span-2"><label>الاسم <span class="req">*</span></label><input class="input @error('name') is-invalid @enderror" name="name" value="{{ old('name', $customer->name) }}" required maxlength="150"></div>
            <div class="field"><label>الرقم القومي</label><input class="input" name="nat_id" value="{{ old('nat_id', auth()->user()->isAdmin() ? $customer->nat_id : '') }}" maxlength="14" inputmode="numeric" placeholder="{{ $editing && !$isAdmin && $customer->nat_id ? $customer->maskedNatId() . ' (اتركه فارغاً للإبقاء عليه)' : '14 رقماً' }}" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>الهاتف <span class="req">*</span></label><input class="input" id="phone-input" name="phone" value="{{ old('phone', $customer->phone) }}" required inputmode="tel" dir="ltr" style="text-align:right" placeholder="01xxxxxxxxx"></div>
            <div class="field"><label>هاتف بديل</label><input class="input" name="alt_phone" value="{{ old('alt_phone', $customer->alt_phone) }}" inputmode="tel" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>واتساب</label><input class="input" name="whatsapp" value="{{ old('whatsapp', $customer->whatsapp) }}" inputmode="tel" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>المهنة</label><input class="input" name="job" value="{{ old('job', $customer->job) }}"></div>

            <div class="form-section"><i class="fa-solid fa-location-dot"></i> العنوان والتصنيف</div>
            <div class="field"><label>المحافظة</label><select class="input" name="governorate"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('governorate') as $g)<option @selected(old('governorate', $customer->governorate) === $g)>{{ $g }}</option>@endforeach</select></div>
            <div class="field"><label>المركز / الحي</label><input class="input" name="district" value="{{ old('district', $customer->district) }}"></div>
            <div class="field"><label>العنوان التفصيلي</label><input class="input" name="address" value="{{ old('address', $customer->address) }}"></div>
            <div class="field"><label>قناة التواصل</label><select class="input" name="channel"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('channel') as $g)<option @selected(old('channel', $customer->channel) === $g)>{{ $g }}</option>@endforeach</select></div>
            <div class="field"><label>المركبة المطلوبة</label><select class="input" name="interest"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('vehicle') as $g)<option @selected(old('interest', $customer->interest) === $g)>{{ $g }}</option>@endforeach</select></div>
            <div class="field"><label>جدية العميل</label><select class="input" name="seriousness"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('seriousness') as $g)<option @selected(old('seriousness', $customer->seriousness) === $g)>{{ $g }}</option>@endforeach</select></div>
            <div class="field"><label>الفرع</label><select class="input" name="branch"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('branch') as $g)<option @selected(old('branch', $customer->branch) === $g)>{{ $g }}</option>@endforeach</select></div>
            <div class="field"><label>السن</label><input class="input" type="number" name="age" min="15" max="100" value="{{ old('age', $customer->age) }}"></div>
            <div class="field"><label>المركبة السابقة</label><input class="input" name="previous_vehicle" value="{{ old('previous_vehicle', $customer->previous_vehicle) }}" placeholder="مثال: توكتوك 2020"></div>
            <div class="field"><label>حالة العميل <span class="req">*</span></label><select class="input" name="status" required>@foreach(\App\Models\Customer::STATUSES as $s)<option @selected(old('status', $customer->status) === $s)>{{ $s }}</option>@endforeach</select></div>
            @if($isAdmin)
                <div class="field"><label>الموظف المسؤول</label><select class="input" name="assigned_to"><option value="{{ auth()->id() }}">أنا ({{ auth()->user()->name }})</option>@foreach($staff->where('id', '!=', auth()->id()) as $u)<option value="{{ $u->id }}" @selected(old('assigned_to', $customer->assigned_to) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            @endif
            <div class="field span-3"><label>سبب عدم إتمام البيع</label><textarea class="input" name="loss_reason" rows="2">{{ old('loss_reason', $customer->loss_reason) }}</textarea></div>
            <div class="field span-3"><label>ملاحظات</label><textarea class="input" name="notes" rows="3">{{ old('notes', $customer->notes) }}</textarea></div>

            @unless($editing)
            <div class="form-section" style="margin-top:14px"><i class="fa-solid fa-car"></i> الصفقة والتقسيط <label class="check" style="margin-right:auto"><input type="checkbox" name="with_deal" value="1" id="with-deal" @checked(old('with_deal'))> إضافة صفقة الآن</label></div>
            <div class="span-3" id="deal-block" style="display:none">
                @include('deals._fields', ['deal' => new \App\Models\Deal(['pay_method' => 'تقسيط', 'interest_type' => 'flat', 'months' => 12]), 'prefix' => true])
            </div>
            @endunless
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}">إلغاء</a>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> {{ $editing ? 'حفظ التعديلات' : 'تسجيل العميل' }}</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
const wd = document.getElementById('with-deal'), blk = document.getElementById('deal-block');
if (wd) { const sync = () => blk.style.display = wd.checked ? 'block' : 'none'; wd.addEventListener('change', sync); sync(); }
</script>
@endpush
