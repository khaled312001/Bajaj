@php($mine = $m->sender_id === $me->id)
<div class="bubble-row">
    <div class="bubble {{ $mine ? 'mine' : 'theirs' }}">
        @if(! $mine)<div class="b-sender">{{ $m->sender?->name }}</div>@endif
        @if($m->type === 'voice')
            <audio controls src="{{ route('chat.voice', $m) }}"></audio>
        @elseif($m->type === 'customer_link')
            @if($m->body)<div>{{ $m->body }}</div>@endif
            @if($m->customer)
                <a class="chat-customer-card" href="{{ $m->customer->url ?? route('customers.show', $m->customer) }}" target="_blank">
                    <i class="fa-solid fa-user"></i>
                    <span><b>{{ $m->customer->name }}</b><span>{{ $m->customer->code }} — {{ $m->customer->status }}</span></span>
                </a>
            @endif
        @else
            {{ $m->body }}
        @endif
        <div class="b-time">{{ $m->created_at->format('H:i') }}</div>
    </div>
</div>
