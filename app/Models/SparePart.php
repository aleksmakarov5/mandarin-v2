<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model
{
    use HasFactory;

    protected $table = 'spare_parts';
    // Разрешаем массовое заполнение всех полей кроме ID
    protected $guarded = ['id'];
    protected $fillable = [
        'application_id',
        'name',
        'part_number',
        'count',
        'price_ic',
        'price_pzn',
        'price_original',
        'price_analogue',
        'price_used',
        'price_sto',
        'selected_mrk',
        'status',
        'order_date',
        'delivery_date',
        'comment',
        'is_manual',
        'is_small_parts',
        'created_by_user_id',
        'author_type',
    ];

    protected $casts = [
        'count' => 'integer',
        'price_ic' => 'float',
        'price_pzn' => 'float',
        'price_original' => 'float',
        'price_analogue' => 'float',
        'price_used' => 'float',
        'price_sto' => 'float',
        'is_manual' => 'boolean',
        'is_small_parts' => 'boolean',
        'order_date' => 'date:Y-m-d',
        'delivery_date' => 'date:Y-m-d',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withDefault([
            'name' => 'Пользователь',
        ]);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SparePartHistory::class, 'spare_part_id')->orderBy('id', 'desc');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SparePartComment::class, 'spare_part_id')->orderBy('created_at', 'asc');
    }

    public function latestComment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SparePartComment::class, 'spare_part_id')->latestOfMany();
    }
}