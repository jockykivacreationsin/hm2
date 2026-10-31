<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $table = 'document_types';

    public $timestamps = false;

    protected $fillable = [
        'type_code',
        'type_name',
        'snomed_code',
        'snomed_display',
        'sort_order',
        'is_active',
        'created_on',
    ];

    public function documents()
    {
        return $this->hasMany(PatientDocument::class, 'document_type_id');
    }
}
