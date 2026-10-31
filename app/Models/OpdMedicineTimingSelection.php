<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpdMedicineTimingSelection extends Model
{
    protected $table = 'opd_medicine_timing_selections';

    public $timestamps = false;

    protected $fillable = [
        'opd_medicine_id',
        'timing_id',
        'created_on',
    ];

    public function timing()
    {
        return $this->belongsTo(MedicineTimingOfDay::class, 'timing_id')->withDefault();
    }

    public function medicine()
    {
        return $this->belongsTo(OpdMedicine::class, 'opd_medicine_id');
    }
}
