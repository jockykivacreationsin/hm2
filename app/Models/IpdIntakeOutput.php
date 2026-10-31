<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_intake_output: id, intake_output_date, ipd_id, bed_id, remarks, user_id
class IpdIntakeOutput extends Model
{
    protected $table = 'ipd_intake_output';
    public $timestamps = false;
    protected $fillable = ['intake_output_date', 'ipd_id', 'bed_id', 'remarks', 'user_id'];
    public function ipd()    { return $this->belongsTo(Ipd::class); }
    public function details(){ return $this->hasMany(IpdIntakeOutputDetail::class, 'ipd_intake_output_id'); }
}
