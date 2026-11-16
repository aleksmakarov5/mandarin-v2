<?php
// app/Models/History.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class History extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'user_id', 'status_id', 'finance_status_id', 'comment'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class);
    }

    public function financeStatus(): BelongsTo
    {
        return $this->belongsTo(FinanceStatus::class);
    }
}