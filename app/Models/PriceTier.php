<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_width' => 'float',
            'max_width' => 'float',
            'min_height' => 'float',
            'max_height' => 'float',
            'unit_price' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
