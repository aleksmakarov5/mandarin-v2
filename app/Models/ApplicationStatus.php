<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $table = 'application_statuses';

    protected $fillable = [
        'name',
        'code',
        'color',
        'bg_color',
        'text_color',
        'time',
        'is_day',
        'user_group_id',
    ];

    protected $casts = [
        'time' => 'integer',
        'is_day' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id')->withDefault([
            'name' => 'Общий статус',
        ]);
    }

    public function applications(): HasMany
    {
        $foreignKey = Schema::hasColumn('applications', 'id_status') ? 'id_status' : 'status_id';
        return $this->hasMany(Application::class, $foreignKey);
    }
}