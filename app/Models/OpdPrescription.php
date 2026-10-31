<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// opd_prescriptions: id, prescription_date_time, opd_id, doctor_id,
//   pathology_tests, radiology_tests, procedures, findings, follow_up_detail
class OpdPrescription extends Model
{
    protected $table = 'opd_prescriptions';
    public $timestamps = false;
    protected $fillable = [
        'prescription_date_time', 'opd_id', 'doctor_id',
        'pathology_tests', 'radiology_tests', 'procedures',
        'findings', 'follow_up_detail',
    ];
    public function opd()     { return $this->belongsTo(Opd::class); }
    public function doctor()  { return $this->belongsTo(StaffMember::class, 'doctor_id')->withDefault(); }
    public function medicines(){ return $this->hasMany(OpdMedicine::class, 'opd_prescription_id'); }
}
