<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Estimate extends Model
{
    use HasFactory;

    protected $table = 'estimates';

    protected $fillable = [
        'application_id',
        'id_application',
        'price_sp_full',
        'price_sp_half',
        'price_repair',
        'price_paint',
        'price_zip',
        'price_work',
        'price_repair_half',
        'price_repair_full',
        'add_pay',
        'price_repair_full_md2',
        'fact',
    ];

    protected $casts = [
        'price_sp_full' => 'float',
        'price_sp_half' => 'float',
        'price_repair' => 'float',
        'price_paint' => 'float',
        'price_zip' => 'float',
        'price_work' => 'float',
        'price_repair_half' => 'float',
        'price_repair_full' => 'float',
        'add_pay' => 'float',
        'price_repair_full_md2' => 'float',
        'fact' => 'float',
    ];

    public function application(): BelongsTo
    {
        $foreignKey = Schema::hasColumn('estimates', 'application_id')
            ? 'application_id'
            : 'id_application';

        return $this->belongsTo(Application::class, $foreignKey);
    }
}