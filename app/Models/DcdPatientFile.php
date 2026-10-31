<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdPatientFile extends Model
{
    protected $table = 'dcd_patient_files';
    public $timestamps = false;
    protected $guarded = ['id'];
    public function dcd() { return $this->belongsTo(Dcd::class); }
}
