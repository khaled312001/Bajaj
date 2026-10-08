@extends('layouts.app')
@php($editing = $deal->exists)
@section('title', $editing ? 'تعديل صفقة' : 'صفقة جديدة')
@section('heading', ($editing ? 'تعديل صفقة' : 'صفقة جديدة') . ' — ' . $customer->name)

@section('content')
<form method="POST" action="{{ $editing ? route('deals.update', $deal) : route('deals.store', $customer) }}" class="card">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card-body">
        @if($editing && $deal->payments()->exists())
            <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i><div>هذه الصفقة بها دفعات مسجلة. عند تعديل بيانات التقسيط يُعاد بناء الجدول وتُوزَّع الدفعات تلقائياً على الأقساط.</div></div>
        @endif
        @include('deals._fields', ['prefix' => false])
        <div class="form-actions">
            <a class="btn btn-ghost" href="{{ route('customers.show', $customer) }}">إلغاء</a>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> حفظ الصفقة</button>
        </div>
    </div>
</form>
@endsection
