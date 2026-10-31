<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_treatments: id, treatment_date_time, ipd_id, doctor_id, bed_id, remarks, user_id
class IpdTreatment extends Model
{
    protected $table = 'ipd_treatments';
    public $timestamps = false;
    protected $fillable = [
        'treatment_date_time', 'ipd_id', 'doctor_id', 'bed_id', 'remarks', 'user_id',
    ];
    public function ipd()    { return $this->belongsTo(Ipd::class); }
    public function doctor() { return $this->belongsTo(StaffMember::class, 'doctor_id')->withDefault(); }
    public function details(){ return $this->hasMany(IpdTreatmentDetail::class, 'ipd_treatment_id'); }
}
