<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opd extends Model
{
    protected $table = 'opds';
    public $timestamps = false;

    protected $fillable = [
        'opd_date_time', 'token_number', 'patient_id', 'charge_department_id',
        'consultant_doctor_id', 'height', 'weight', 'bp', 'pulse', 'temperature',
        'respiration', 'spo2', 'symptoms', 'remarks', 'opd_charges', 'payment_mode',
        'status', 'source', 'created_on', 'user_id',
    ];

    public function patient()      { return $this->belongsTo(Patient::class); }
    public function doctor()       { return $this->belongsTo(StaffMember::class, 'consultant_doctor_id')->withDefault(); }
    public function prescriptions(){ return $this->hasMany(OpdPrescription::class); }
    public function services()     { return $this->hasMany(OpdService::class); }
    public function ipd()          { return $this->hasOne(Ipd::class, 'opd_id'); }
    public function dcd()          { return $this->hasOne(Dcd::class, 'opd_id'); }

    // ABDM helpers
    public function careContexts() { return $this->hasMany(AbdmCareContext::class, 'opd_id'); }
    public function latestCareContext() { return $this->hasOne(AbdmCareContext::class, 'opd_id')->latestOfMany(); }
}
