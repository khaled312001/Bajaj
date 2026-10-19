{{-- One field of the public lead form. Params: $f (LeadForm field), $vehicles --}}
@php($req = $f['required'] ? 'required' : '')
@php($ph = $f['placeholder'])
<div class="field {{ $f['width'] === 'full' ? 'span-2' : '' }}" data-key="{{ $f['key'] }}">
    <label>{{ $f['label'] }} @if($f['required'])<span class="req">*</span>@endif</label>
    @switch($f['type'])
        @case('phone')
            <input class="input" name="{{ $f['key'] }}" value="{{ old($f['key']) }}" {{ $req }} maxlength="20" inputmode="tel" dir="ltr" style="text-align:right" placeholder="{{ $ph }}">
            @break
        @case('textarea')
            <textarea class="input" name="{{ $f['key'] }}" rows="3" {{ $req }} maxlength="1000" placeholder="{{ $ph }}">{{ old($f['key']) }}</textarea>
            @break
        @case('number')
            <input class="input" type="number" step="any" name="{{ $f['key'] }}" value="{{ old($f['key']) }}" {{ $req }} placeholder="{{ $ph }}">
            @break
        @case('date')
            <input class="input" type="date" name="{{ $f['key'] }}" value="{{ old($f['key']) }}" {{ $req }}>
            @break
        @case('select')
        @case('vehicle')
            <select class="input" name="{{ $f['key'] }}" {{ $req }}>
                <option value="">{{ $ph ?: '— اختر —' }}</option>
                @foreach($f['type'] === 'vehicle' ? $vehicles : $f['options'] as $o)<option @selected(old($f['key']) === $o)>{{ $o }}</option>@endforeach
            </select>
            @break
        @default
            <input class="input" name="{{ $f['key'] }}" value="{{ old($f['key']) }}" {{ $req }} maxlength="{{ $f['key'] === 'name' ? 150 : 250 }}" placeholder="{{ $ph }}">
    @endswitch
</div>
