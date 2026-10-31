<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpdLabInvestigationNote extends Model
{
    protected $table = 'ipd_lab_investigation_notes';
    public $timestamps = false;
    protected $fillable = ['ipd_service_id', 'note', 'updated_on', 'user_id'];

    public function staff()
    {
        return $this->belongsTo(StaffMember::class, 'user_id')->withDefault();
    }
}
