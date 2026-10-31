<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdMedicine extends Model
{
    protected $table = 'dcd_medicines';
    public $timestamps = false;
    protected $fillable = [
        'dcd_prescription_id', 'pharma_product_id', 'dose_quantity',
        'frequency_type_id', 'meal_relation_id', 'duration_days',
        'medical_condition_id', 'refills_allowed', 'created_on', 'updated_on',
    ];
    public function prescription() { return $this->belongsTo(DcdPrescription::class, 'dcd_prescription_id'); }
    public function product()      { return $this->belongsTo(PharmaProduct::class, 'pharma_product_id')->withDefault(); }
    public function frequency()    { return $this->belongsTo(MedicineFrequencyType::class, 'frequency_type_id')->withDefault(); }
    public function mealRelation() { return $this->belongsTo(MedicineMealRelation::class, 'meal_relation_id')->withDefault(); }
    public function condition()    { return $this->belongsTo(MedicalCondition::class, 'medical_condition_id')->withDefault(); }
    public function timings()      { return $this->hasMany(DcdMedicineTimingSelection::class, 'dcd_medicine_id'); }
}
