<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Lookup extends Model
{
    public const TYPES = [
        'governorate' => 'المحافظات',
        'vehicle' => 'المركبات',
        'channel' => 'قنوات التواصل',
        'finance_entity' => 'جهات التقسيط',
        'followup_reason' => 'أسباب المتابعة',
        'installment_stage' => 'مراحل التقسيط',
        'seriousness' => 'جدية العميل',
        'branch' => 'الفروع',
    ];

    protected $fillable = ['type', 'name', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget('lookups.all');
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return string[] active names of a given type, ordered. */
    public static function list(string $type): array
    {
        $all = Cache::remember('lookups.all', 3600, function () {
            return self::where('is_active', true)->orderBy('sort')->orderBy('id')->get(['type', 'name'])
                ->groupBy('type')->map(fn ($g) => $g->pluck('name')->all())->all();
        });

        return $all[$type] ?? [];
    }
}
