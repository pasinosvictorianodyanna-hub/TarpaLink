<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Job extends Model
{
    protected $guarded = [];

    /** Every status the enum allows, in workflow order. */
    public const STATUS_LABELS = [
        'quoted' => 'Quoted',
        'awaiting_proof' => 'Proof sent',
        'approved' => 'Approved',
        'in_production' => 'In production',
        'ready' => 'Ready for pickup',
        'collected' => 'Collected',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'due_at' => 'datetime',
            'collected_at' => 'datetime',
            'total' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Job $job) {
            if (! $job->reference) {
                do {
                    $candidate = 'TL-'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
                } while (static::where('reference', $candidate)->exists());

                $job->reference = $candidate;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JobLine::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(Proof::class)->orderByDesc('version');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(JobActivity::class)->latest();
    }

    public function latestProof(): ?Proof
    {
        return $this->proofs->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** "36×48 in" — the size half of the spec, in the units shops actually quote. */
    public function sizeLabel(): string
    {
        return $this->inches($this->spec['width_in'] ?? null)
            .'×'.$this->inches($this->spec['height_in'] ?? null).' in';
    }

    /** "8 pcs · Eyelets, Hemming" — quantity plus the finishing the customer chose. */
    public function specSummary(): string
    {
        $parts = [(int) ($this->spec['qty'] ?? 0).' pcs'];

        // Option ids are stored, not names: a shop can rename "Hemming" to
        // "Hem & stitch" without rewriting every job already on file.
        $chosen = collect($this->spec['options'] ?? [])
            ->map(fn (array $option) => $this->service?->options
                ->firstWhere('id', $option['id'] ?? null)?->name)
            ->filter()
            ->values()
            ->all();

        if ($chosen !== []) {
            $parts[] = implode(', ', $chosen);
        }

        return implode(' · ', $parts);
    }

    /** Whole inches print without the `.0` that a float cast would leave behind. */
    private function inches(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }

    /** @return array<string, string> status => human label, for selects and badges. */
    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'ready' => 'ready',
            'collected' => 'done',
            'cancelled' => 'cancel',
            'awaiting_proof' => 'proof',
            default => 'live',
        };
    }

    /** Steps for the job status timeline. */
    public function timeline(): array
    {
        $order = ['quoted', 'awaiting_proof', 'approved', 'in_production', 'ready', 'collected'];
        $currentIndex = array_search($this->status, $order, true);

        return collect($order)->map(function ($step, $i) use ($currentIndex) {
            return [
                'key' => $step,
                'label' => match ($step) {
                    'quoted' => 'Quote confirmed',
                    'awaiting_proof' => 'Proof sent',
                    'approved' => 'You approved the proof',
                    'in_production' => 'On press',
                    'ready' => 'Ready for pickup',
                    'collected' => 'Collected',
                },
                'done' => $currentIndex !== false && $i <= $currentIndex,
                'current' => $currentIndex !== false && $i === $currentIndex,
            ];
        })->all();
    }
}
