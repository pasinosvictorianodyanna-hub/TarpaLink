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

    /**
     * The one-line title on a job row.
     *
     * Area-priced work is identified by its size — "Tarpaulin 36 × 48 in" is what
     * the customer ordered and what the counter will measure. Piece work is
     * identified by the count instead: "Flyers 8.3×11.7 in" tells nobody
     * anything they can check against a stack of 200.
     */
    public function titleLine(): string
    {
        $name = $this->service?->name ?? 'Job';

        if (($this->service?->unit ?? 'pc') === 'sqft') {
            return $name.' '.str_replace('×', ' × ', $this->sizeLabel());
        }

        return $name.' · '.(int) ($this->spec['qty'] ?? 0).' pcs';
    }

    /** The quiet line under the price: what happens next, or who is holding it up. */
    public function nextStepLine(): string
    {
        return match ($this->status) {
            'quoted' => 'Awaiting payment',
            'awaiting_proof' => 'Waiting on you',
            'approved', 'in_production' => $this->due_at
                ? 'Ready '.$this->due_at->format('D, g:i A')
                : 'On press',
            'ready' => 'Collect anytime',
            'collected' => $this->updated_at->format('d M Y'),
            'cancelled' => 'Cancelled',
            default => '',
        };
    }

    /**
     * The customer-facing progress rail.
     *
     * There is no event log behind this — `job_activities` is the shop's audit
     * trail and is empty for most jobs — so every step is reconstructed from the
     * record that proves it: the payment for "placed", the proof row for "file
     * received" and "approved", the due date for "ready". A step with no `at` is
     * one the data cannot date, and the view shows it without a timestamp rather
     * than inventing one.
     *
     * @return array<int, array{label:string, detail:?string, at:mixed, state:string}>
     */
    public function progressSteps(): array
    {
        $proof = $this->latestProof();
        $payment = $this->payment;
        $paid = $payment && $payment->status === 'paid';

        $order = ['quoted', 'awaiting_proof', 'approved', 'in_production', 'ready', 'collected'];
        $reached = array_search($this->status, $order, true);
        $reached = $reached === false ? 0 : $reached;

        $steps = [
            [0, $paid ? 'Order placed & paid' : 'Order placed',
                $payment
                    ? ($payment->provider === 'paymongo' ? 'PayMongo' : ucfirst($payment->provider))
                        .' ₱'.number_format($payment->amount / 100, 2)
                    : 'Cash at pickup',
                $this->created_at],
            [1, 'Design file received',
                $proof ? 'Proof v'.$proof->version.' prepared by the shop' : 'Waiting for the shop to upload artwork',
                $proof?->created_at],
            [2, 'Proof approved by you',
                $proof ? 'Version '.$proof->version : null,
                $proof?->approved_at],
            [3, 'Printing in progress',
                ($this->shop?->name ?? 'The shop').' · matrix v'.$this->matrix_version,
                null],
            [4, 'Ready for pickup',
                'Bring your order number to the counter',
                $this->due_at],
            [5, 'Collected',
                null,
                $this->status === 'collected' ? $this->updated_at : null],
        ];

        return array_map(function (array $step) use ($reached) {
            [$index, $label, $detail, $at] = $step;

            $state = match (true) {
                $index < $reached => 'done',
                $index === $reached => 'now',
                default => 'todo',
            };

            // A collected job has nothing "in progress"; the rail reads as a
            // finished list rather than stopping halfway with a spinner.
            if ($this->status === 'collected' && $index <= $reached) {
                $state = 'done';
            }

            return ['label' => $label, 'detail' => $detail, 'at' => $at, 'state' => $state];
        }, $steps);
    }

    /** Everything after the shop name on a job row: quantity, finishing, reference. */
    public function rowMeta(): string
    {
        return implode(' · ', array_filter([
            $this->specSummary(),
            '#'.$this->reference,
        ]));
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
