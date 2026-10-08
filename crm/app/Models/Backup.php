<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = ['filename', 'size', 'tables', 'rows', 'kind', 'status', 'error', 'created_by'];

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function path(): string
    {
        return storage_path('app/backups/' . $this->filename);
    }
}
