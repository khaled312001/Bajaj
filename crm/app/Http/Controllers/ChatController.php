<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = Conversation::forUser($user)->with(['userOne:id,name,role', 'userTwo:id,name,role'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->with(['messages' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->orderByDesc('last_message_at')->get();

        return view('chat.index', [
            'conversations' => $conversations,
            'people' => User::where('id', '!=', $user->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'active' => null,
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $other = User::findOrFail($data['user_id']);
        abort_if($other->id === $request->user()->id, 422, 'لا يمكنك بدء محادثة مع نفسك.');
        $conversation = Conversation::between($request->user(), $other);

        return redirect()->route('chat.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $user = $request->user();

        $conversations = Conversation::forUser($user)->with(['userOne:id,name,role', 'userTwo:id,name,role'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->with(['messages' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->orderByDesc('last_message_at')->get();

        $messages = $conversation->messages()->with(['sender:id,name', 'customer:id,name,code,status'])->orderBy('created_at')->get();
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $user->id)->update(['read_at' => now()]);

        return view('chat.index', [
            'conversations' => $conversations,
            'people' => User::where('id', '!=', $user->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'active' => $conversation,
            'messages' => $messages,
        ]);
    }

    /** Polling endpoint: messages after a given id, marking the other side's messages as read. */
    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $user = $request->user();
        $after = (int) $request->query('after', 0);

        $q = $conversation->messages()->with(['sender:id,name', 'customer:id,name,code,status'])->where('id', '>', $after)->orderBy('created_at');
        $messages = $q->get();
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $user->id)->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn ($m) => ['id' => $m->id, 'html' => view('chat._bubble', ['m' => $m, 'me' => $user])->render()]),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $user = $request->user();

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'audio' => ['nullable', 'file', 'mimes:webm,ogg,mp4,m4a,mp3,wav', 'max:5120'],
        ]);
        abort_if(empty($data['body']) && empty($data['customer_id']) && ! $request->hasFile('audio'), 422, 'الرسالة فارغة.');

        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->storeAs('chat-voice', now()->format('Ymd_His_') . bin2hex(random_bytes(4)) . '.' . $request->file('audio')->getClientOriginalExtension(), 'local');
            $message = $conversation->messages()->create(['sender_id' => $user->id, 'type' => 'voice', 'voice_path' => $path, 'created_at' => now()]);
        } elseif (! empty($data['customer_id'])) {
            $customer = Customer::query()->visibleTo($user)->findOrFail($data['customer_id']);
            $message = $conversation->messages()->create(['sender_id' => $user->id, 'type' => 'customer_link', 'customer_id' => $customer->id, 'body' => $data['body'] ?? null, 'created_at' => now()]);
        } else {
            $message = $conversation->messages()->create(['sender_id' => $user->id, 'type' => 'text', 'body' => $data['body'], 'created_at' => now()]);
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load(['sender:id,name', 'customer:id,name,code,status']);

        return response()->json(['message' => ['id' => $message->id, 'html' => view('chat._bubble', ['m' => $message, 'me' => $user])->render()]]);
    }

    /** Lightweight customer lookup for the chat composer's "share customer file" picker. */
    public function searchCustomers(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['customers' => []]);
        }
        $customers = Customer::query()->visibleTo($request->user())->search($term)->latest('id')->limit(8)->get(['id', 'name', 'code', 'status']);

        return response()->json(['customers' => $customers]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = ChatMessage::whereHas('conversation', fn ($q) => $q->forUser($user))->whereNull('read_at')->where('sender_id', '!=', $user->id)->count();

        return response()->json(['count' => $count]);
    }

    /** Stream a voice note back; only the two conversation participants may hear it. */
    public function voice(Request $request, ChatMessage $message)
    {
        abort_unless($message->type === 'voice' && $message->voice_path, 404);
        $this->authorizeConversation($request, $message->conversation);

        abort_unless(Storage::disk('local')->exists($message->voice_path), 404);

        return Storage::disk('local')->response($message->voice_path);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $id = $request->user()->id;
        abort_unless($conversation->user_one_id === $id || $conversation->user_two_id === $id, 403);
    }
}
