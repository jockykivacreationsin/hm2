<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdPrescription extends Model
{
    protected $table = 'dcd_prescriptions';
    public $timestamps = false;
    protected $fillable = [
        'prescription_date_time', 'dcd_id', 'doctor_id',
        'pathology_tests', 'radiology_tests', 'procedures', 'findings',
    ];
    public function dcd()     { return $this->belongsTo(Dcd::class); }
    public function doctor()  { return $this->belongsTo(StaffMember::class, 'doctor_id')->withDefault(); }
    public function medicines(){ return $this->hasMany(DcdMedicine::class, 'dcd_prescription_id'); }
}
