<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'sparepart_status_id',
        'name',
        'part_number',
        'order_number',
        'price_ic_full',
        'price_ic_half',
        'price_original',
        'price_analogue',
        'price_used',
        'selected_price',
        'selected_mrk',
        'status',
        'date_price',
        'date_wait',
        'comment',
    ];

    protected $casts = [
        'price_ic_full' => 'decimal:2',
        'price_ic_half' => 'decimal:2',
        'price_original' => 'decimal:2',
        'price_analogue' => 'decimal:2',
        'price_used' => 'decimal:2',
        'date_price' => 'date',
        'date_wait' => 'date',
        'selected_price' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function sparepartStatus(): BelongsTo
    {
        return $this->belongsTo(SparepartStatus::class);
    }

    // Хелпер получения итоговой выбранной цены
    public function getEffectivePriceAttribute(): float
    {
        return match ($this->selected_price) {
            1 => (float) $this->price_original,
            2 => (float) $this->price_analogue,
            3 => (float) $this->price_used,
            default => 0.0,
        };
    }
}