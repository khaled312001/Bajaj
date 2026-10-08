@extends('layouts.app')
@section('title', $def['title'])
@section('heading', $def['title'])
@section('sub', 'راجع البيانات ثم أصدر المستند')

@section('content')
<form method="POST" action="{{ route('documents.store', $type) }}" class="card">
    @csrf
    <input type="hidden" name="deal_id" value="{{ $deal?->id }}"><input type="hidden" name="customer_id" value="{{ $customer?->id }}">
    <div class="card-head"><h3><i class="fa-solid fa-file-pen"></i> بيانات المستند</h3>
        @if($customer)<span class="badge blue">{{ $customer->name }} — {{ $customer->code }}</span>@endif</div>
    <div class="card-body">
        @if($errors->has('deal_id'))<div class="alert alert-error mb"><i class="fa-solid fa-circle-xmark"></i><div>{{ $errors->first('deal_id') }}</div></div>@endif
        <div class="form-grid">
        @foreach($def['fields'] as $f)
            @php([$name, $label, $kind] = $f)
            @php($val = old($name, $values[$name] ?? ''))
            @if($kind === 'check')
                <div class="field span-2"><label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $values[$name] ?? false))> {{ $label }}</label></div>
            @elseif($kind === 'textarea')
                <div class="field span-3"><label>{{ $label }}</label><textarea class="input" rows="4" name="{{ $name }}">{{ $val }}</textarea></div>
            @else
                <div class="field"><label>{{ $label }}</label>
                    <input class="input" name="{{ $name }}" value="{{ $val }}" type="{{ in_array($kind, ['money', 'number']) ? 'number' : ($kind === 'date' ? 'date' : 'text') }}" @if($kind === 'money') step="0.01" min="0" @endif></div>
            @endif
        @endforeach
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> إصدار المستند (PDF)</button>
            <a class="btn btn-ghost" href="{{ route('documents.index') }}">إلغاء</a></div>
    </div>
</form>
@endsection
