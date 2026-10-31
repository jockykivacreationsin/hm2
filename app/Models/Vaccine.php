<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vaccine extends Model
{
    protected $table = 'vaccines';

    public $timestamps = false;

    protected $fillable = [
        'vaccine_name',
        'short_name',
        'snomed_code',
        'snomed_display',
        'is_uip',
        'total_doses',
        'schedule_note',
        'default_route_id',
        'default_site',
        'sort_order',
        'is_active',
        'created_on',
    ];

    public function defaultRoute()
    {
        return $this->belongsTo(MedicineRoute::class, 'default_route_id')->withDefault();
    }

    public function doseSchedule()
    {
        return $this->hasMany(VaccineDoseSchedule::class, 'vaccine_id')->orderBy('dose_number');
    }

    public function immunizationRecords()
    {
        return $this->hasMany(ImmunizationRecord::class, 'vaccine_id');
    }
}
