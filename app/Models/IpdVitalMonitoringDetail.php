<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_vital_monitoring_detail: id, ipd_vital_monitoring_id, vital_monitoring_time,
//   bp, pulse, temperature, respiration, spo2, fbs, rbs, intake, output
class IpdVitalMonitoringDetail extends Model
{
    protected $table = 'ipd_vital_monitoring_detail';
    public $timestamps = false;
    protected $fillable = [
        'ipd_vital_monitoring_id', 'vital_monitoring_time',
        'bp', 'pulse', 'temperature', 'respiration', 'spo2',
        'fbs', 'rbs', 'intake', 'output',
    ];
    public function monitoring() { return $this->belongsTo(IpdVitalMonitoring::class, 'ipd_vital_monitoring_id'); }
}
