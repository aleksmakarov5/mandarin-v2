<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentUploadBatch extends Model
{
    protected $fillable = [
        'application_id',
        'document_id',
        'document_type_id',
        'batch_type',
        'status',
        'total_count',
        'uploaded_count',
        'processed_count',
        'failed_count',
        'created_by_user_id',
        'client_batch_key',
        'context',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'uploaded_count' => 'integer',
        'processed_count' => 'integer',
        'failed_count' => 'integer',
        'context' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(
            DocumentType::class,
            'document_type_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            DocumentUploadItem::class,
            'batch_id'
        );
    }
}