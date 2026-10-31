<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_consultations: id, consultation_date_time, ipd_id, doctor_id, remarks, user_id
class IpdConsultation extends Model
{
    protected $table = 'ipd_consultations';
    public $timestamps = false;
    protected $fillable = ['consultation_date_time', 'ipd_id', 'doctor_id', 'remarks', 'user_id'];
    public function ipd()    { return $this->belongsTo(Ipd::class); }
    public function doctor() { return $this->belongsTo(StaffMember::class, 'doctor_id')->withDefault(); }
}
