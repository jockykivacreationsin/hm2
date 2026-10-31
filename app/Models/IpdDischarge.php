<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_discharge: id, discharge_date_time, ipd_id, diagnosis,
//   condition_on_discharge, follow_up_detail, note, urgent_care_condition,
//   approved, approved_doctor_id
class IpdDischarge extends Model
{
    protected $table = 'ipd_discharge';
    public $timestamps = false;
    protected $fillable = [
        'discharge_date_time', 'ipd_id', 'diagnosis', 'condition_on_discharge',
        'follow_up_detail', 'note', 'urgent_care_condition',
        'approved', 'approved_doctor_id',
    ];
    public function ipd()           { return $this->belongsTo(Ipd::class); }
    public function approvedDoctor(){ return $this->belongsTo(StaffMember::class, 'approved_doctor_id')->withDefault(); }
}
