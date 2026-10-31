<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdService extends Model
{
    protected $table = 'dcd_services';
    public $timestamps = false;
    protected $guarded = ['id'];
    public function dcd()             { return $this->belongsTo(Dcd::class); }
    public function chargeDepartment(){ return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
    public function details()         { return $this->hasMany(DcdServiceDetail::class, 'dcd_service_id'); }
    public function labNote()           { return $this->hasOne(DcdLabInvestigationNote::class, 'dcd_service_id'); }
    public function radiologyNote()     { return $this->hasOne(DcdRadiologyNote::class, 'dcd_service_id'); }
    public function procedureNote()      { return $this->hasOne(DcdProcedureNote::class, 'dcd_service_id'); }
}
