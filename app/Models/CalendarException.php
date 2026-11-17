<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CalendarException extends Model
{
    use HasFactory;

    protected $table = 'calendar_exceptions';

    protected $fillable = [
        'date',
        'is_workday',
        'comment',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_workday' => 'boolean',
    ];

    /**
     * Сброс кэша календаря при любых изменениях
     */
    protected static function booted(): void
    {
        static::saved(function ($model) {
            $year = date('Y', strtotime($model->date));
            Cache::forget("calendar_exceptions_{$year}");
            Cache::forget('calendar_exceptions_all');
        });

        static::deleted(function ($model) {
            $year = date('Y', strtotime($model->date));
            Cache::forget("calendar_exceptions_{$year}");
            Cache::forget('calendar_exceptions_all');
        });
    }
}