<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImmunizationRecord extends Model
{
    protected $table = 'immunization_records';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'vaccine_id',
        'dose_number',
        'status',
        'occurrence_date_time',
        'lot_number',
        'expiry_date',
        'manufacturer',
        'brand_name',
        'route_id',
        'site',
        'performer_staff_id',
        'opd_id',
        'ipd_id',
        'dcd_id',
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

    public function route()
    {
        return $this->belongsTo(MedicineRoute::class, 'route_id')->withDefault();
    }

    public function performer()
    {
        return $this->belongsTo(StaffMember::class, 'performer_staff_id')->withDefault();
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

    public function recommendations()
    {
        return $this->hasMany(ImmunizationRecommendation::class, 'source_record_id');
    }
}
