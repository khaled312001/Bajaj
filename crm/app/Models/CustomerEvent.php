<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'user_id', 'type', 'description', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getIconAttribute(): array
    {
        return match ($this->type) {
            'created' => ['fa-user-plus', 'blue'],
            'updated' => ['fa-pen', 'gray'],
            'assigned' => ['fa-right-left', 'purple'],
            'followup' => ['fa-calendar-check', 'sky'],
            'followup_done' => ['fa-circle-check', 'green'],
            'deal' => ['fa-car', 'amber'],
            'payment' => ['fa-money-bill-wave', 'green'],
            'status' => ['fa-flag', 'amber'],
            'import' => ['fa-file-import', 'purple'],
            default => ['fa-circle-info', 'gray'],
        };
    }
}
