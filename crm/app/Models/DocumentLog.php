<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DocumentLog extends Model
{
    protected $fillable = ['type', 'serial', 'legacy_ref', 'customer_id', 'deal_id', 'user_id', 'customer_name', 'product_name', 'amount', 'payload', 'printed_at'];

    protected function casts(): array
    {
        return ['printed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function setData(array $d): void
    {
        $this->payload = Crypt::encryptString(json_encode($d, JSON_UNESCAPED_UNICODE));
    }

    public function data(): array
    {
        try {
            return $this->payload ? (json_decode(Crypt::decryptString($this->payload), true) ?: []) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
