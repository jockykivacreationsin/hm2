<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_prescriptions: id, prescription_date_time, ipd_id, doctor_id,
//   pathology_tests, radiology_tests, procedures, findings
class IpdPrescription extends Model
{
    protected $table = 'ipd_prescriptions';
    public $timestamps = false;
    protected $fillable = [
        'prescription_date_time', 'ipd_id', 'doctor_id',
        'pathology_tests', 'radiology_tests', 'procedures', 'findings',
    ];
    public function ipd()     { return $this->belongsTo(Ipd::class); }
    public function doctor()  { return $this->belongsTo(StaffMember::class, 'doctor_id')->withDefault(); }
    public function medicines(){ return $this->hasMany(IpdMedicine::class, 'ipd_prescription_id'); }
}
