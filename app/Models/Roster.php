<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// roster: id, roster_date, staff_id, designation, shift
class Roster extends Model
{
    protected $table = 'roster';
    public $timestamps = false;
    protected $fillable = ['roster_date', 'staff_id', 'designation', 'shift'];
    public function staff() { return $this->belongsTo(StaffMember::class, 'staff_id')->withDefault(); }
}
