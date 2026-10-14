{{-- Vehicle <option>s: priced products first (price shown), then the other vehicle types. Pass $selected. --}}
@php($voPrices = \App\Models\Product::where('is_active', true)->orderBy('sort')->orderBy('name')->pluck('price', 'name'))
@php($voOthers = array_values(array_diff(\App\Models\Lookup::list('vehicle'), $voPrices->keys()->all())))
@php($voSelected = (string) ($selected ?? ''))
<option value="">— اختر —</option>
@if($voSelected !== '' && ! isset($voPrices[$voSelected]) && ! in_array($voSelected, $voOthers, true))<option value="{{ $voSelected }}" selected>{{ $voSelected }}</option>@endif
@if($voPrices->isNotEmpty())<optgroup label="المنتجات بالأسعار">@foreach($voPrices as $name => $price)<option value="{{ $name }}" @selected($voSelected === $name)>{{ $name }} — {{ number_format($price) }} ج.م</option>@endforeach</optgroup>@endif
@if($voOthers)<optgroup label="أنواع أخرى">@foreach($voOthers as $name)<option value="{{ $name }}" @selected($voSelected === $name)>{{ $name }}</option>@endforeach</optgroup>@endif
