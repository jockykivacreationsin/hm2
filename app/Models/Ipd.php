<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ipd extends Model
{
    protected $table = 'ipds';
    public $timestamps = false;

    protected $fillable = [
        'admission_date_time', 'opd_id', 'dcd_id', 'patient_id',
        'tpa_provider_id', 'tpa_number', 'tpa_credit_limit',
        'consultant_doctor_id', 'bed_id', 'previous_medical_note',
        'status', 'created_on', 'user_id',
    ];

    /**
     * The legacy app stores a current admission as 'New Admission' (and only ever flips it to 'Discharged');
     * 'Admitted' is what earlier Laravel code wrote. Both mean "currently admitted".
     */
    public const ADMITTED_STATUSES = ['New Admission', 'Admitted'];
    public const NEW_ADMISSION     = 'New Admission';

    public function scopeAdmitted($query)
    {
        return $query->whereIn($query->qualifyColumn('status'), self::ADMITTED_STATUSES);
    }

    public function getIsAdmittedAttribute(): bool
    {
        return in_array($this->status, self::ADMITTED_STATUSES, true);
    }

    public function patient()       { return $this->belongsTo(Patient::class); }
    public function opd()           { return $this->belongsTo(Opd::class, 'opd_id')->withDefault(); }
    public function doctor()        { return $this->belongsTo(StaffMember::class, 'consultant_doctor_id')->withDefault(); }
    public function bed()           { return $this->belongsTo(Bed::class)->withDefault(); }
    public function tpaProvider()   { return $this->belongsTo(TpaProvider::class, 'tpa_provider_id')->withDefault(); }
    public function patientFile()   { return $this->hasOne(IpdPatientFile::class); }
    public function prescriptions() { return $this->hasMany(IpdPrescription::class); }
    public function services()      { return $this->hasMany(IpdService::class); }
    public function consultations() { return $this->hasMany(IpdConsultation::class); }
    public function treatments()    { return $this->hasMany(IpdTreatment::class); }
    public function vitalMonitorings() { return $this->hasMany(IpdVitalMonitoring::class); }
    public function intakeOutputs() { return $this->hasMany(IpdIntakeOutput::class); }
    public function patientBeds()   { return $this->hasMany(IpdPatientBed::class); }
    public function discharge()     { return $this->hasOne(IpdDischarge::class); }
    public function careContexts()  { return $this->hasMany(AbdmCareContext::class, 'ipd_id'); }

    public function getIpdRefAttribute(): string
    {
        return 'IPD' . str_pad($this->id, 7, '0', STR_PAD_LEFT);
    }
}
