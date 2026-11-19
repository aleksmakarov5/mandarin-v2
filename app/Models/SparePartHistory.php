<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePartHistory extends Model
{
    use HasFactory;

    protected $table = 'spare_part_histories';

    protected $fillable = [
        'spare_part_id',
        'application_id',
        'user_id',
        'action_type', // created, updated, deleted, supplier_selected
        'field_name',
        'old_value',
        'new_value',
    ];

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}