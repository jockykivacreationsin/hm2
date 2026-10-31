<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// staff_attendance: id, attendance_date, check_in_time, check_out_time,
//   staff_id, status, remarks, created_at, user_id
class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';
    public $timestamps = false;
    protected $fillable = [
        'attendance_date', 'check_in_time', 'check_out_time',
        'staff_id', 'status', 'remarks', 'created_at', 'user_id',
    ];
    public function staff() { return $this->belongsTo(StaffMember::class, 'staff_id')->withDefault(); }
}
