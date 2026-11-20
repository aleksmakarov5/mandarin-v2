<?php
// app/Models/PlanWork.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanWork extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'name', 'price', 'count_hours', 'summ'];

    protected $casts = [
        'price' => 'decimal:2',
        'count_hours' => 'decimal:2',
        'summ' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlanWorkHistory::class, 'plan_work_id')->latest();
    }
}