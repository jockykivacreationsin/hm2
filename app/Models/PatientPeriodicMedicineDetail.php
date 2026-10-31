<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// patient_periodic_medicine_detail: id, pm_id, medicine_name, dose_quantity, dose_interval, remarks
class PatientPeriodicMedicineDetail extends Model
{
    protected $table = 'patient_periodic_medicine_detail';
    public $timestamps = false;
    protected $fillable = ['pm_id', 'medicine_name', 'dose_quantity', 'dose_interval', 'remarks'];
}
