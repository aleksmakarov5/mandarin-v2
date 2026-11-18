<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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

    public function applications(): HasMany
    {
        // Поддерживаем как finance_status_id, так и id_finance_status
        $foreignKey = Schema::hasColumn('applications', 'finance_status_id')
            ? 'finance_status_id'
            : 'id_finance_status';

        return $this->hasMany(Application::class, $foreignKey);
    }
}