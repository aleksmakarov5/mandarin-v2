<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = [
        'legacy_doc_status_id',
        'code',
        'name',
        'category',
        'document_kind',
        'workflow_code',
        'is_photo',
        'is_archive',
        'is_generated',
        'allows_multiple',
        'allows_upload',
        'allows_generation',
        'default_sto_view',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_photo' => 'boolean',
        'is_archive' => 'boolean',
        'is_generated' => 'boolean',
        'allows_multiple' => 'boolean',
        'allows_upload' => 'boolean',
        'allows_generation' => 'boolean',
        'default_sto_view' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            Document::class,
            'document_type_id',
            'id'
        );
    }
}