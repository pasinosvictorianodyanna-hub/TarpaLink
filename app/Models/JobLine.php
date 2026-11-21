<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
