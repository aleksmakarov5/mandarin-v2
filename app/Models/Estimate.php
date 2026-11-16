<?php
// app/Models/Estimate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estimate extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'sum_works', 'sum_parts', 'sum_total', 'comment'];

    protected $casts = [
        'sum_works' => 'decimal:2',
        'sum_parts' => 'decimal:2',
        'sum_total' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}