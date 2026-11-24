<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'paint_materials_approved',
        'paint_materials_plan',
        'ic_repair_sum',
        'ic_defect_sum',
        'dds_income_sum',
        'dds_income_date',
        'dds_sto_pay_date',
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
        'paint_materials_approved' => 'decimal:2',
        'paint_materials_plan' => 'decimal:2',
        'ic_repair_sum' => 'decimal:2',
        'ic_defect_sum' => 'decimal:2',
        'dds_income_sum' => 'decimal:2',
        'dds_income_date' => 'date:Y-m-d',
        'dds_sto_pay_date' => 'date:Y-m-d',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}