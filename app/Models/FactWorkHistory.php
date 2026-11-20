<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactWorkHistory extends Model
{
    use HasFactory;

    protected $table = 'fact_work_histories';

    protected $fillable = [
        'application_id',
        'fact_work_id',
        'user_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault([
            'name' => 'Система',
        ]);
    }
}