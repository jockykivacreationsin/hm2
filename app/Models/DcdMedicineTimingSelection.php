<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DcdMedicineTimingSelection extends Model
{
    protected $table = 'dcd_medicine_timing_selections';

    public $timestamps = false;

    protected $fillable = [
        'dcd_medicine_id',
        'timing_id',
        'created_on',
    ];

    public function timing()
    {
        return $this->belongsTo(MedicineTimingOfDay::class, 'timing_id')->withDefault();
    }

    public function medicine()
    {
        return $this->belongsTo(DcdMedicine::class, 'dcd_medicine_id');
    }
}
