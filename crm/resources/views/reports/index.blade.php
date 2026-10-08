@extends('layouts.app')
@section('title', 'التقارير')
@section('heading', 'مركز التقارير')
@section('sub', 'اختر تقريراً لعرضه وتصديره إلى Excel')

@section('content')
<div class="grid g3">
    @foreach($reports as $key => $r)
        <a href="{{ route('reports.show', $key) }}" class="card report-card">
            <div class="ico"><i class="fa-solid {{ $r['icon'] }}"></i></div>
            <h3>{{ $r['title'] }}</h3><p>{{ $r['desc'] }}</p>
        </a>
    @endforeach
</div>
@endsection
