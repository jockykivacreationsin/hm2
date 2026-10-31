<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// dcd_discharge: id, discharge_date_time, dcd_id, diagnosis,
//   condition_on_discharge, follow_up_detail, note, urgent_care_condition,
//   discharged_to, approved, approved_doctor_id
class DcdDischarge extends Model
{
    protected $table = 'dcd_discharge';
    public $timestamps = false;
    protected $fillable = [
        'discharge_date_time', 'dcd_id', 'diagnosis', 'condition_on_discharge',
        'follow_up_detail', 'note', 'urgent_care_condition',
        'discharged_to', 'approved', 'approved_doctor_id',
    ];
    public function dcd()           { return $this->belongsTo(Dcd::class); }
    public function approvedDoctor(){ return $this->belongsTo(StaffMember::class, 'approved_doctor_id')->withDefault(); }
}
