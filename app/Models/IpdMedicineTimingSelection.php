<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpdMedicineTimingSelection extends Model
{
    protected $table = 'ipd_medicine_timing_selections';

    public $timestamps = false;

    protected $fillable = [
        'ipd_medicine_id',
        'timing_id',
        'created_on',
    ];

    public function timing()
    {
        return $this->belongsTo(MedicineTimingOfDay::class, 'timing_id')->withDefault();
    }

    public function medicine()
    {
        return $this->belongsTo(IpdMedicine::class, 'ipd_medicine_id');
    }
}
