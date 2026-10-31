<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_medicines: id, ipd_prescription_id, pharma_product_id, dose_quantity,
//   frequency_type_id, meal_relation_id, duration_days, medical_condition_id,
//   created_on, updated_on
class IpdMedicine extends Model
{
    protected $table = 'ipd_medicines';
    public $timestamps = false;
    protected $fillable = [
        'ipd_prescription_id', 'pharma_product_id', 'dose_quantity',
        'frequency_type_id', 'meal_relation_id', 'duration_days',
        'medical_condition_id', 'created_on', 'updated_on',
    ];
    public function prescription() { return $this->belongsTo(IpdPrescription::class, 'ipd_prescription_id'); }
    public function product()      { return $this->belongsTo(PharmaProduct::class, 'pharma_product_id')->withDefault(); }
    public function frequency()    { return $this->belongsTo(MedicineFrequencyType::class, 'frequency_type_id')->withDefault(); }
    public function mealRelation() { return $this->belongsTo(MedicineMealRelation::class, 'meal_relation_id')->withDefault(); }
    public function condition()    { return $this->belongsTo(MedicalCondition::class, 'medical_condition_id')->withDefault(); }
    public function timings()      { return $this->hasMany(IpdMedicineTimingSelection::class, 'ipd_medicine_id'); }
}
