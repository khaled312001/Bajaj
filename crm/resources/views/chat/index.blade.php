@extends('layouts.app')
@section('title', 'المحادثات')
@section('heading', 'المحادثات')
@section('sub', 'محادثات داخلية بينك وبين الإدارة والزملاء')

@section('content')
@php($me = auth()->user())
<div class="chat-wrap">
    <div class="chat-list {{ $active ? 'has-active' : '' }}">
        <div class="chat-list-head">
            <select class="input" id="new-chat-picker" onchange="if(this.value){ var f=document.getElementById('start-chat-form'); f.user_id.value=this.value; f.submit(); }">
                <option value="">+ بدء محادثة جديدة...</option>
                @foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->role === 'admin' ? 'مدير' : 'موظف' }}</option>@endforeach
            </select>
            <form id="start-chat-form" method="POST" action="{{ route('chat.start') }}" style="display:none"><input type="hidden" name="user_id">@csrf</form>
        </div>
        @forelse($conversations as $c)
            @php($other = $c->otherUser($me))
            @php($last = $c->messages->first())
            <a href="{{ route('chat.show', $c) }}" class="chat-item {{ $active && $active->id === $c->id ? 'active' : '' }}">
                <div class="avatar">{{ $other->initials }}</div>
                <div class="ci-body">
                    <div class="ci-name"><span>{{ $other->name }}</span>
                        @if($c->unread_count)<span class="nav-badge">{{ $c->unread_count }}</span>@endif</div>
                    <div class="ci-prev">
                        @if($last)
                            @if($last->type === 'voice')<i class="fa-solid fa-microphone"></i> رسالة صوتية
                            @elseif($last->type === 'customer_link')<i class="fa-solid fa-link"></i> مشاركة ملف عميل
                            @else{{ \Illuminate\Support\Str::limit($last->body, 40) }}
                            @endif
                        @else لا توجد رسائل بعد @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="chat-empty" style="padding:24px"><i class="fa-regular fa-comments" style="font-size:30px"></i><span>ابدأ محادثة جديدة من القائمة أعلاه</span></div>
        @endforelse
    </div>

    <div class="chat-thread {{ $active ? 'active' : '' }}" id="chat-thread" data-messages-url="{{ $active ? route('chat.messages', $active) : '' }}" data-send-url="{{ $active ? route('chat.send', $active) : '' }}" data-last-id="{{ $active && $messages->isNotEmpty() ? $messages->last()->id : 0 }}">
        @if($active)
            @php($other = $active->otherUser($me))
            <div class="chat-thread-head">
                <a href="{{ route('chat.index') }}" class="btn btn-xs btn-ghost chat-back" id="chat-back"><i class="fa-solid fa-arrow-right"></i></a>
                <div class="avatar">{{ $other->initials }}</div>
                <div><b>{{ $other->name }}</b><div class="small muted">{{ $other->role === 'admin' ? 'مدير النظام' : 'موظف خدمة عملاء' }}</div></div>
            </div>
            <div class="chat-messages" id="chat-messages">
                @foreach($messages as $m)
                    @include('chat._bubble', ['m' => $m, 'me' => $me])
                @endforeach
            </div>
            <form class="chat-composer" id="chat-composer" autocomplete="off" data-search-url="{{ route('chat.search-customers') }}">
                <button type="button" class="chat-ic-btn" id="emoji-btn" title="إيموجي"><i class="fa-regular fa-face-smile"></i></button>
                <div class="emoji-pop" id="emoji-pop"></div>
                <button type="button" class="chat-ic-btn" id="share-customer-btn" title="مشاركة ملف عميل"><i class="fa-solid fa-user-plus"></i></button>
                <div class="emoji-pop customer-pop" id="customer-pop">
                    <input type="text" class="input" id="customer-pop-search" placeholder="ابحث بالاسم / الهاتف / الكود...">
                    <div id="customer-pop-results" class="customer-pop-results"></div>
                </div>
                <textarea class="input" name="body" rows="1" placeholder="اكتب رسالة..." id="chat-input"></textarea>
                <button type="button" class="chat-ic-btn" id="mic-btn" title="رسالة صوتية"><i class="fa-solid fa-microphone"></i></button>
                <button type="submit" class="chat-ic-btn" style="background:var(--blue-600);color:#fff;border-color:var(--blue-600)" title="إرسال"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        @else
            <div class="chat-empty"><i class="fa-regular fa-comment-dots" style="font-size:36px"></i><span>اختر محادثة من القائمة أو ابدأ واحدة جديدة</span></div>
        @endif
    </div>
</div>
@push('scripts')<script src="{{ asset('js/chat.js') }}?v={{ filemtime(public_path('js/chat.js')) }}"></script>@endpush
@endsection
