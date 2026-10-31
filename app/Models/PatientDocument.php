<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientDocument extends Model
{
    protected $table = 'patient_documents';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'document_type_id',
        'title',
        'document_date',
        'file_path',
        'original_file_name',
        'mime_type',
        'file_size',
        'sha256',
        'opd_id',
        'ipd_id',
        'dcd_id',
        'status',
        'remarks',
        'created_on',
        'user_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id')->withDefault();
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id')->withDefault();
    }

    public function opd()
    {
        return $this->belongsTo(Opd::class, 'opd_id')->withDefault();
    }

    public function ipd()
    {
        return $this->belongsTo(Ipd::class, 'ipd_id')->withDefault();
    }

    public function dcd()
    {
        return $this->belongsTo(Dcd::class, 'dcd_id')->withDefault();
    }
}
