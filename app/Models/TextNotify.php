<?php
// app/Models/TextNotify.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextNotify extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'body'];
}