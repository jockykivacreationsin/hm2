<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpdTreatmentDetail extends Model
{
    protected $table = 'ipd_treatment_detail';
    public $timestamps = false;
    protected $fillable = [
        'ipd_treatment_id', 'pharma_product_id', 'dose_quantity',
        'frequency_type_id', 'meal_relation_id', 'duration_days',
        'medical_condition_id', 'created_at', 'updated_at',
    ];
    public function product() { return $this->belongsTo(PharmaProduct::class, 'pharma_product_id')->withDefault(); }
}
