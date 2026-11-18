<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceStatus extends Model
{
    use HasFactory;

    protected $table = 'finance_statuses';

    protected $fillable = [
        'name',
        'color',
        'bg_color',
        'text_color',
        'time',
        'is_day',
    ];

    protected $casts = [
        'time' => 'integer',
        'is_day' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'finance_status_id');
    }
}