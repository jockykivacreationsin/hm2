<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// departments: id, department
class HrsDepartment extends Model
{
    protected $table = 'departments';
    public $timestamps = false;
    protected $fillable = ['department'];
    public function staffMembers() { return $this->hasMany(StaffMember::class, 'department_id'); }
}
