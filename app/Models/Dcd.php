<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dcd extends Model
{
    protected $table = 'dcds';
    public $timestamps = false;

    protected $fillable = [
        'dcd_date_time', 'opd_id', 'patient_id', 'consultant_doctor_id',
        'bed_id', 'previous_medical_note', 'status', 'created_on', 'user_id',
    ];

    public function patient()       { return $this->belongsTo(Patient::class); }
    public function opd()           { return $this->belongsTo(Opd::class, 'opd_id')->withDefault(); }
    public function doctor()        { return $this->belongsTo(StaffMember::class, 'consultant_doctor_id')->withDefault(); }
    public function bed()           { return $this->belongsTo(Bed::class)->withDefault(); }
    public function prescriptions() { return $this->hasMany(DcdPrescription::class); }
    public function services()      { return $this->hasMany(DcdService::class); }
    public function patientFile()   { return $this->hasOne(DcdPatientFile::class); }
    public function discharge()     { return $this->hasOne(DcdDischarge::class); }
    public function careContexts()  { return $this->hasMany(AbdmCareContext::class, 'dcd_id'); }

    public function getDcdRefAttribute(): string
    {
        return 'DCD' . str_pad($this->id, 7, '0', STR_PAD_LEFT);
    }
}
