<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraExpense extends Model
{
    protected $table = 'extra_expenses';

    protected $fillable = [
        'application_id',
        'name',
        'expense_type',
        'sum',
        'comment',
        'author_type',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'sum' => 'decimal:2',
        'author_type' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ExtraExpenseHistory::class, 'extra_expense_id');
    }
}