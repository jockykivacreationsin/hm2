<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_payments: id, payment_date_time, ipd_id, charge_department_id,
//   payment_mode, amount, ids, user_id
class IpdPayment extends Model
{
    protected $table = 'ipd_payments';
    public $timestamps = false;
    protected $fillable = [
        'payment_date_time', 'ipd_id', 'charge_department_id',
        'payment_mode', 'amount', 'ids', 'user_id',
    ];
    public function ipd()             { return $this->belongsTo(Ipd::class); }
    public function chargeDepartment(){ return $this->belongsTo(ChargeDepartment::class, 'charge_department_id')->withDefault(); }
}
