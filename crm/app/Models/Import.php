<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    protected $fillable = ['user_id', 'type', 'filename', 'path', 'status', 'total', 'valid', 'created', 'updated', 'failed', 'errors'];

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
