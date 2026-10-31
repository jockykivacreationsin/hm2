<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// staff_salary_appraisal: id, appraisal_date, staff_id, current_salary, new_salary, remarks, user_id
class StaffSalaryAppraisal extends Model
{
    protected $table = 'staff_salary_appraisal';
    public $timestamps = false;
    protected $fillable = ['appraisal_date', 'staff_id', 'current_salary', 'new_salary', 'remarks', 'user_id'];
    public function staff() { return $this->belongsTo(StaffMember::class, 'staff_id')->withDefault(); }
}
