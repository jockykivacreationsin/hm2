<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// opd_medicines: id, opd_prescription_id, opd_id, pharma_product_id,
//   dose_quantity, frequency_type_id, meal_relation_id, duration_days,
//   medical_condition_id, refills_allowed, remarks, created_on, updated_on
class OpdMedicine extends Model
{
    protected $table = 'opd_medicines';

    public $timestamps = false;

    protected $fillable = [
        'opd_prescription_id',
        'opd_id',
        'pharma_product_id',
        'dose_quantity',
        'frequency_type_id',
        'meal_relation_id',
        'duration_days',
        'medical_condition_id',
        'refills_allowed',
        'remarks',
        'created_on',
        'updated_on',
    ];

    public function prescription()
    {
        return $this->belongsTo(OpdPrescription::class, 'opd_prescription_id');
    }

    public function product()
    {
        return $this->belongsTo(PharmaProduct::class, 'pharma_product_id')->withDefault();
    }

    public function frequency()
    {
        return $this->belongsTo(MedicineFrequencyType::class, 'frequency_type_id')->withDefault();
    }

    public function mealRelation()
    {
        return $this->belongsTo(MedicineMealRelation::class, 'meal_relation_id')->withDefault();
    }

    public function condition()
    {
        return $this->belongsTo(MedicalCondition::class, 'medical_condition_id')->withDefault();
    }

    public function timings()
    {
        return $this->hasMany(OpdMedicineTimingSelection::class, 'opd_medicine_id');
    }
}
