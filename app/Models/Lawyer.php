<?php
// app/Models/Lawyer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lawyer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'theme'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}