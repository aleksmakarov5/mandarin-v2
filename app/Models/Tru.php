<?php
// app/Models/Tru.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tru extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'true_var_id', 'comment'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function trueVar(): BelongsTo
    {
        return $this->belongsTo(TrueVar::class);
    }
}