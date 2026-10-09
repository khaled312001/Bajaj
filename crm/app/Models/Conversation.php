<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A 1:1 chat thread between two users (admin<->employee or employee<->employee). */
class Conversation extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id);
    }

    public function otherUser(User $me): User
    {
        return $this->user_one_id === $me->id ? $this->userTwo : $this->userOne;
    }

    /** Find or create the single conversation between two users, regardless of argument order. */
    public static function between(User $a, User $b): self
    {
        [$one, $two] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return self::firstOrCreate(['user_one_id' => $one, 'user_two_id' => $two]);
    }
}
