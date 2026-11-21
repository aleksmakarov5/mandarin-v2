<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraExpenseHistory extends Model
{
    protected $table = 'extra_expense_histories';

    protected $fillable = [
        'extra_expense_id',
        'application_id',
        'user_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}