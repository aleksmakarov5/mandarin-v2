<?php
// app/Models/ChatAttachment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'file_name', 'location'];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}