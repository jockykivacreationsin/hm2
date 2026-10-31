<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// opd_services: id, service_date_time, service_title, opd_id,
//   opd_charge_id, charge_department_id, status, created_on, user_id
class OpdService extends Model
{
    protected $table = 'opd_services';
    public $timestamps = false;
    protected $fillable = [
        'service_date_time', 'service_title', 'opd_id', 'opd_charge_id',
        'charge_department_id', 'status', 'created_on', 'user_id',
    ];
    public function opd()            { return $this->belongsTo(Opd::class); }
    public function chargeDepartment(){ return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
    public function billingCharge()  { return $this->belongsTo(BillingCharge::class, 'opd_charge_id')->withDefault(); }
    public function details()        { return $this->hasMany(OpdServiceDetail::class, 'opd_service_id'); }
    public function labNote()        { return $this->hasOne(OpdLabInvestigationNote::class, 'opd_service_id'); }
    public function radiologyNote()  { return $this->hasOne(OpdRadiologyNote::class, 'opd_service_id'); }
    public function procedureNote()  { return $this->hasOne(OpdProcedureNote::class, 'opd_service_id'); }
}
