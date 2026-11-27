<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'application_id',
        'document_type_id',
        'title',
        'source_type',
        'lifecycle_status',
        'visibility_scope',
        'is_archived',
        'archived_at',
        'archived_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'legacy_attachment_id',
        'legacy_doc_status_id',
        'source_message_id',
        'document_date',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'document_date' => 'date:Y-m-d',
        'content_revision' => 'integer',
        'archive_revision' => 'integer',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)
            ->where('is_current', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function allFiles(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}