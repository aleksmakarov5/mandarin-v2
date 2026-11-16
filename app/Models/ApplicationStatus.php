<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}