<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImmunizationRecommendation extends Model
{
    protected $table = 'immunization_recommendations';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'vaccine_id',
        'dose_number',
        'due_date',
        'status',
        'source_record_id',
        'fulfilled_record_id',
        'remarks',
        'created_on',
        'updated_on',
        'user_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id')->withDefault();
    }

    public function vaccine()
    {
        return $this->belongsTo(Vaccine::class, 'vaccine_id')->withDefault();
    }

    public function sourceRecord()
    {
        return $this->belongsTo(ImmunizationRecord::class, 'source_record_id')->withDefault();
    }

    public function fulfilledRecord()
    {
        return $this->belongsTo(ImmunizationRecord::class, 'fulfilled_record_id')->withDefault();
    }
}
