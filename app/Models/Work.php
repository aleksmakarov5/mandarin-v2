<?php
// app/Models/Work.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Work extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'work_group_id',
        'name',
        'price',
        'count_hours',
        'summ',
        'price_service',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'count_hours' => 'decimal:2',
        'summ' => 'decimal:2',
        'price_service' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class, 'work_group_id');
    }
}