<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_patient_beds: id, allot_date_time, left_date_time, ipd_id, bed_id, remarks, user_id
class IpdPatientBed extends Model
{
    protected $table = 'ipd_patient_beds';
    public $timestamps = false;
    protected $fillable = [
        'allot_date_time', 'left_date_time', 'ipd_id', 'bed_id', 'remarks', 'user_id',
    ];
    public function bed() { return $this->belongsTo(Bed::class)->withDefault(); }
}
