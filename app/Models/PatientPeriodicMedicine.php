<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// patient_periodic_medicines: id, medicine_date_time, next_medicine_date, patient_id,
//   disease_ids, delivery, remarks
class PatientPeriodicMedicine extends Model
{
    protected $table = 'patient_periodic_medicines';
    public $timestamps = false;
    protected $fillable = [
        'medicine_date_time', 'next_medicine_date', 'patient_id',
        'disease_ids', 'delivery', 'remarks',
    ];
    public function patient()  { return $this->belongsTo(Patient::class); }
    public function medicines(){ return $this->hasMany(PatientPeriodicMedicineDetail::class, 'pm_id'); }
}
