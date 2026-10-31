<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// charge_categories: id, charge_department_id, charge_category
class ChargeCategory extends Model
{
    protected $table = 'charge_categories';
    public $timestamps = false;
    protected $fillable = ['charge_department_id', 'charge_category'];
    public function department() { return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
}
