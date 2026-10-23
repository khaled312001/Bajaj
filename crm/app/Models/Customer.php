<?php

namespace App\Models;

use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use SoftDeletes;

    public const STATUSES = ['مفتوحة', 'جاري التقسيط', 'منفذة', 'مفقودة'];

    protected $fillable = [
        'code', 'name', 'nat_id', 'job', 'phone', 'alt_phone', 'whatsapp', 'governorate',
        'district', 'address', 'channel', 'interest', 'age', 'seriousness', 'previous_vehicle', 'branch', 'loss_reason',
        'contacted_at', 'status', 'notes', 'created_by', 'assigned_to', 'is_dealer', 'shop_name',
    ];

    protected $hidden = ['nat_id', 'nat_id_hash'];

    protected function casts(): array
    {
        return ['nat_id' => 'encrypted', 'is_dealer' => 'boolean', 'contacted_at' => 'datetime', 'assigned_at' => 'datetime', 'assignment_seen_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $c) {
            if (empty($c->code)) {
                $c->code = self::generateCode();
            }
        });

        static::saving(function (Customer $c) {
            $c->nat_id_hash = $c->nat_id ? self::hashNatId($c->nat_id) : null;
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(3)) . random_int(100, 999);
            $code = strtr($code, ['O' => 'X', 'I' => 'Y', 'L' => 'Z', '0' => '7', '1' => '8']);
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public static function hashNatId(string $natId): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $natId), config('app.key'));
    }

    /** Normalize Egyptian phone numbers (Arabic digits, spaces, +20 prefix). */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $phone = strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0020')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '20') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 2);
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '1')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /* ---- Relations ---- */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(Followup::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerEvent::class)->latest('created_at')->latest('id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class)->latest('created_at')->latest('id');
    }

    public function reassignmentRequests(): HasMany
    {
        return $this->hasMany(ReassignmentRequest::class);
    }

    /** True when a pending follow-up has gone unactioned for 48h+ (eligible for a takeover request). */
    public function hasStaleFollowup(): bool
    {
        return $this->followups()->where('status', 'pending')->where('due_date', '<=', today()->subDays(2))->exists();
    }

    /* ---- Scopes ---- */

    /**
     * Restrict customers an agent may see: everyone when the global scope is "all" or the employee has the
     * "customers_all" permission (role profile or personal override), otherwise only their own customers.
     */
    /**
     * Hand the customer to an employee. When someone else does it (admin, leads, an approved transfer) the customer
     * shows as "new" for that employee — dashboard notice, badge, top of their list — until they open the file.
     */
    public function assignTo(int $userId, ?User $by = null): void
    {
        $this->forceFill([
            'assigned_to' => $userId, 'assigned_at' => now(),
            'assignment_seen_at' => $by && $by->id === $userId ? now() : null,
        ])->save();
    }

    public function isNewFor(User $user): bool
    {
        return (int) $this->assigned_to === $user->id && $this->assigned_at && ! $this->assignment_seen_at;
    }

    public function scopeNewlyAssignedTo(Builder $q, User $user): Builder
    {
        return $q->where('customers.assigned_to', $user->id)->whereNotNull('customers.assigned_at')->whereNull('customers.assignment_seen_at');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdmin() || Settings::get('agent_scope', 'own') === 'all' || Permissions::allows($user, 'customers_all')) {
            return $q;
        }

        return $q->where(function ($w) use ($user) {
            $w->where('customers.assigned_to', $user->id)->orWhere('customers.created_by', $user->id);
        });
    }

    /**
     * Lists show an employee only their own customers, but any customer they search for can be opened
     * (to add a follow-up or ask for a transfer). Changing a colleague's customer needs "edit" on customers_all.
     */
    public function editableBy(User $user): bool
    {
        return $user->isAdmin() || Settings::get('agent_scope', 'own') === 'all'
            || (int) $this->assigned_to === $user->id || (int) $this->created_by === $user->id
            || Permissions::allows($user, 'customers_all', 'edit');
    }

    /** Digits only (with spaces, +, dashes or Arabic digits): a phone number or part of one. */
    public static function looksLikePhone(string $term): bool
    {
        return (bool) preg_match('/^[\d\s+\-()٠-٩]+$/u', $term) && strlen((string) self::normalizePhone($term)) >= 3;
    }

    /** A whole customer code: six letters/digits with at least one letter, e.g. 8NZ789. */
    public static function looksLikeCode(string $term): bool
    {
        return (bool) preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{6}$/', $term);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        // phone numbers are searched only when the term is a number: a code like 8NZ789 must not match
        // every phone that happens to contain "8789"
        $phone = self::looksLikePhone($term) ? self::normalizePhone($term) : null;
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $q->where(function ($w) use ($term, $like, $phone) {
            $w->where('customers.name', 'like', $like)
                ->orWhere('customers.code', 'like', $like);
            if ($phone) {
                $w->orWhere('customers.phone', 'like', '%' . $phone . '%')
                    ->orWhere('customers.alt_phone', 'like', '%' . $phone . '%')
                    ->orWhere('customers.whatsapp', 'like', '%' . $phone . '%');
            }
            if (preg_match('/^\d{14}$/', $term)) {
                $w->orWhere('customers.nat_id_hash', self::hashNatId($term));
            }
        });
    }

    /* ---- Accessors ---- */
    /** "علي محمد (معرض ابوعبده - القصير)": a dealer's name with his shop, as printed on his papers. */
    public function getDisplayNameAttribute(): string
    {
        $shop = trim((string) $this->shop_name);

        return $shop !== '' && ! str_contains((string) $this->name, $shop) ? $this->name . ' (' . $shop . ')' : (string) $this->name;
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'منفذة' => 'green',
            'جاري التقسيط' => 'amber',
            'مفتوحة' => 'blue',
            'مفقودة' => 'red',
            default => 'gray',
        };
    }

    public function maskedNatId(): ?string
    {
        $v = $this->nat_id;
        return $v ? str_repeat('•', max(0, strlen($v) - 4)) . substr($v, -4) : null;
    }

    public function whatsappLink(): ?string
    {
        $n = self::normalizePhone($this->whatsapp ?: $this->phone);
        return $n ? 'https://wa.me/20' . ltrim($n, '0') : null;
    }

    /** Plain-text summary for pasting into WhatsApp, e.g. to send to a financing company. */
    public function basicInfoCopyText(bool $showFullNatId): string
    {
        $lines = ['بيانات العميل', 'الاسم: ' . $this->name];
        if ($this->phone) {
            $lines[] = 'الهاتف: ' . $this->phone;
        }
        if ($this->alt_phone && $this->alt_phone !== $this->phone) {
            $lines[] = 'الهاتف البديل: ' . $this->alt_phone;
        }
        if ($this->whatsapp && $this->whatsapp !== $this->phone && $this->whatsapp !== $this->alt_phone) {
            $lines[] = 'واتساب: ' . $this->whatsapp;
        }
        if ($this->governorate) {
            $lines[] = 'المحافظة: ' . $this->governorate;
        }
        if ($this->district) {
            $lines[] = 'المركز: ' . $this->district;
        }
        if ($this->address) {
            $lines[] = 'العنوان: ' . $this->address;
        }
        $natId = $showFullNatId ? $this->nat_id : $this->maskedNatId();
        if ($natId) {
            $lines[] = 'الرقم القومي: ' . $natId;
        }
        $entity = $this->deals()->whereNotNull('finance_entity')->latest()->value('finance_entity');
        if ($entity) {
            $lines[] = 'جهة التقسيط: ' . $entity;
        }

        return implode("\n", $lines);
    }
}
