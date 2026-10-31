<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// staff_salary_payments: id, salary_date_time, staff_id, amount, payment_mode, remarks
class StaffSalaryPayment extends Model
{
    protected $table = 'staff_salary_payments';
    public $timestamps = false;
    protected $fillable = ['salary_date_time', 'staff_id', 'amount', 'payment_mode', 'remarks'];
    public function staff() { return $this->belongsTo(StaffMember::class, 'staff_id')->withDefault(); }
}
