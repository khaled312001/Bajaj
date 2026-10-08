<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'warranty', 'requirements', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['price' => 'float', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // products double as the "vehicle" dropdown everywhere (customer interest, deals, filters)
        static::saved(function (Product $p) {
            Lookup::firstOrCreate(['type' => 'vehicle', 'name' => $p->name], ['sort' => 100 + $p->id]);
            if ($p->wasChanged('name') && $old = $p->getOriginal('name')) {
                Lookup::where('type', 'vehicle')->where('name', $old)->delete();
            }
        });
        static::deleted(fn (Product $p) => Lookup::where('type', 'vehicle')->where('name', $p->name)->delete());
    }
}
