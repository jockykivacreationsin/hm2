<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaccineDoseSchedule extends Model
{
    protected $table = 'vaccine_dose_schedule';

    public $timestamps = false;

    protected $fillable = [
        'vaccine_id',
        'dose_number',
        'interval_days',
        'notes',
        'created_on',
    ];

    public function vaccine()
    {
        return $this->belongsTo(Vaccine::class, 'vaccine_id');
    }
}
