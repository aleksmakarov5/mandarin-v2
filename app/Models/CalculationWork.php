<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationWork extends Model
{
    use HasFactory;

    protected $table = 'works';

    protected $fillable = [
        'application_id',
        'name',
        'price',
        'count_hours',
        'hours_pzn',
        'sum_sk',
        'sum_pzn',
        'is_paint',
        'work_category',
        'author_type',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'price' => 'float',
        'count_hours' => 'float',
        'hours_pzn' => 'float',
        'sum_sk' => 'float',
        'sum_pzn' => 'float',
        'is_paint' => 'boolean',
        'author_type' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CalculationWorkHistory::class, 'work_id');
    }
}