<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// dcd_lab_investigation_notes: id, dcd_service_id, note, updated_on, user_id
class DcdLabInvestigationNote extends Model
{
    protected $table = 'dcd_lab_investigation_notes';
    public $timestamps = false;
    protected $fillable = ['dcd_service_id', 'note', 'updated_on', 'user_id'];

    public function service() { return $this->belongsTo(DcdService::class, 'dcd_service_id'); }
    public function staff() { return $this->belongsTo(StaffMember::class, 'user_id')->withDefault(); }
}
