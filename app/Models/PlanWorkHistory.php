<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanWorkHistory extends Model
{
    use HasFactory;

    protected $table = 'plan_work_histories';

    protected $fillable = [
        'application_id',
        'plan_work_id',
        'user_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
    ];

    /**
     * Пользователь, выполнивший действие
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault([
            'name' => 'Система',
        ]);
    }

    /**
     * Дело, к которому относится история
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Сама плановая работа (может быть null, если работа удалена)
     */
    public function planWork(): BelongsTo
    {
        return $this->belongsTo(PlanWork::class, 'plan_work_id');
    }
}