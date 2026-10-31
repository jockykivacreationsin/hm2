<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patients';
    public $timestamps = false;

    protected $fillable = [
        'patient_category_id', 'patient_name', 'guardian_name', 'gender',
        'date_of_birth', 'age', 'blood_group', 'mobile_number', 'email',
        'alternate_mobile_number', 'address', 'esm_name', 'card_number',
        'service_number', 'claim_id', 'photo', 'aadhaar_number',
        'abha_number', 'abha_address', 'status', 'created_on', 'user_id',
    ];

    public function patientCategory() { return $this->belongsTo(PatientCategory::class); }
    public function opds()            { return $this->hasMany(Opd::class); }
    public function ipds()            { return $this->hasMany(Ipd::class); }
    public function dcds()            { return $this->hasMany(Dcd::class); }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('patient_category_id', $categoryId);
    }

    /** UHID = settings prefix + padded patients.id + settings suffix; {YY}/{YYYY} = year of created_on (App\Support\Uhid). */
    public function getUhidAttribute(): string
    {
        return \App\Support\Uhid::format($this->id, $this->created_on);
    }
}
