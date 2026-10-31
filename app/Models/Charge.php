<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// charges: id, charge_code, charge_name, charge_department_id, charge_category_id
class Charge extends Model
{
    protected $table = 'charges';
    public $timestamps = false;
    protected $fillable = ['charge_code', 'charge_name', 'charge_department_id', 'charge_category_id'];
    public function chargeDepartment() { return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
    public function department()       { return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
    public function chargeCategory()   { return $this->belongsTo(ChargeCategory::class, 'charge_category_id')->withDefault(); }
}
