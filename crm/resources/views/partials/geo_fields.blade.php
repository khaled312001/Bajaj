{{-- Cascading Egypt governorate → مركز select pair. Params: govName, centerName, govValue, centerValue, required --}}
@php
    $govName = $govName ?? 'governorate';
    $centerName = $centerName ?? 'district';
@endphp
<div class="field">
    <label>المحافظة @if($required ?? false)<span class="req">*</span>@endif</label>
    <select class="input geo-gov" name="{{ $govName }}">
        <option value="">— اختر —</option>
        @if(!empty($govValue) && !in_array($govValue, \App\Support\EgyptGeo::all(), true))<option selected>{{ $govValue }}</option>@endif
        @foreach(\App\Support\EgyptGeo::all() as $g)
            <option @selected(($govValue ?? null) === $g)>{{ $g }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label>المركز</label>
    <select class="input geo-center" name="{{ $centerName }}" data-current="{{ $centerValue ?? '' }}">
        <option value="">— اختر المحافظة أولاً —</option>
    </select>
</div>
