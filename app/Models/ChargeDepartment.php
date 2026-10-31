<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// charge_departments: id, department_name, category, services, division_id, active, icon, sort_order
class ChargeDepartment extends Model
{
    protected $table = 'charge_departments';
    public $timestamps = false;
    protected $fillable = [
        'department_name', 'category', 'services', 'division_id', 'active', 'icon', 'sort_order',
    ];

    public function getChargeDepartmentNameAttribute() { return $this->attributes['department_name'] ?? null; }
    public function setChargeDepartmentNameAttribute($value) { $this->attributes['department_name'] = $value; }

    public function charges()   { return $this->hasMany(Charge::class, 'charge_department_id'); }
    public function categories(){ return $this->hasMany(ChargeCategory::class, 'charge_department_id'); }
}
