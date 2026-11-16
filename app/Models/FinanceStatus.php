<?php
// app/Models/FinanceStatus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}