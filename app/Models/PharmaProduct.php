<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// pharma_products: id, category_id, product_name, dose_unit_id, route_id,
//   administration_method_id, is_active, created_on, updated_on
class PharmaProduct extends Model
{
    protected $table = 'pharma_products';
    public $timestamps = false;
    protected $fillable = [
        'category_id', 'product_name', 'dose_unit_id', 'route_id',
        'administration_method_id', 'is_active', 'created_on', 'updated_on',
    ];
    public function category() { return $this->belongsTo(PharmaProductCategory::class, 'category_id')->withDefault(); }
    public function doseUnit() { return $this->belongsTo(MedicineDoseUnit::class, 'dose_unit_id')->withDefault(); }
    public function route()    { return $this->belongsTo(MedicineRoute::class, 'route_id')->withDefault(); }
}
