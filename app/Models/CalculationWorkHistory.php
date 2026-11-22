<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationWorkHistory extends Model
{
    use HasFactory;

    protected $table = 'calculation_work_histories';

    protected $fillable = [
        'work_id',
        'application_id',
        'user_id',
        'action_type', // created, updated, transferred_to_paint, transferred_to_repair, deleted
        'field_name',
        'old_value',
        'new_value',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(CalculationWork::class, 'work_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}