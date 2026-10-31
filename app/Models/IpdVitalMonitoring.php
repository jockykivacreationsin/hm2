<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_vital_monitoring: id, vital_monitoring_date, ipd_id, bed_id, remarks, user_id
class IpdVitalMonitoring extends Model
{
    protected $table = 'ipd_vital_monitoring';
    public $timestamps = false;
    protected $fillable = [
        'vital_monitoring_date', 'ipd_id', 'bed_id', 'remarks', 'user_id',
    ];
    public function ipd()    { return $this->belongsTo(Ipd::class); }
    public function bed()    { return $this->belongsTo(Bed::class)->withDefault(); }
    public function details(){ return $this->hasMany(IpdVitalMonitoringDetail::class, 'ipd_vital_monitoring_id'); }
}
