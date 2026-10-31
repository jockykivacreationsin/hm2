<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_services: id, service_date_time, service_title, ipd_id,
//   ipd_charge_id, charge_department_id, status, created_on, user_id
class IpdService extends Model
{
    protected $table = 'ipd_services';
    public $timestamps = false;
    protected $fillable = [
        'service_date_time', 'service_title', 'ipd_id', 'ipd_charge_id',
        'charge_department_id', 'status', 'created_on', 'user_id',
    ];
    public function ipd()             { return $this->belongsTo(Ipd::class); }
    public function chargeDepartment(){ return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
    public function billingCharge()   { return $this->belongsTo(BillingCharge::class, 'ipd_charge_id')->withDefault(); }
    public function details()         { return $this->hasMany(IpdServiceDetail::class, 'ipd_service_id'); }
    public function labNote()         { return $this->hasOne(IpdLabInvestigationNote::class, 'ipd_service_id'); }
    public function radiologyNote()   { return $this->hasOne(IpdRadiologyNote::class, 'ipd_service_id'); }
    public function procedureNote()   { return $this->hasOne(IpdProcedureNote::class, 'ipd_service_id'); }
}
