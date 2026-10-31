<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// staff_members: id, joining_date, staff_name, designation_id, department_id,
//   specialities, gender, dob, mobile_number, email, residential_address,
//   permanent_address, qualification, experience_years, aadhaar_number,
//   photo, current_salary, user_role_id, salaried, password, active
class StaffMember extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'staff_members';
    public $timestamps = false;

    protected $fillable = [
        'joining_date', 'staff_name', 'designation_id', 'department_id',
        'specialities', 'gender', 'dob', 'mobile_number', 'email',
        'residential_address', 'permanent_address', 'qualification',
        'experience_years', 'aadhaar_number', 'photo', 'current_salary',
        'user_role_id', 'salaried', 'password', 'active',
        'medical_registration_number', 'registration_council', 'hpr_id', 'hpr_address',
        'trusted_device_token',
    ];

    protected $hidden = ['password'];

    public function role()       { return $this->belongsTo(UserRole::class, 'user_role_id')->withDefault(); }
    public function department() { return $this->belongsTo(HrsDepartment::class, 'department_id')->withDefault(); }
    public function designation(){ return $this->belongsTo(Designation::class, 'designation_id')->withDefault(); }
    public function opds()       { return $this->hasMany(Opd::class, 'consultant_doctor_id'); }
    public function ipds()       { return $this->hasMany(Ipd::class, 'consultant_doctor_id'); }
    public function attendance() { return $this->hasMany(StaffAttendance::class, 'staff_id'); }
}
