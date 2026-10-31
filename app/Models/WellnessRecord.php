<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessRecord extends Model
{
    protected $table = 'wellness_records';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'recorded_at',
        'source',
        'opd_id',
        'ipd_id',
        'dcd_id',
        'remarks',
        'created_on',
        'user_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id')->withDefault();
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

    public function observations()
    {
        return $this->hasMany(WellnessObservation::class, 'wellness_record_id');
    }
}
