<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// pharma_product_categories: id, category_name
class PharmaProductCategory extends Model
{
    protected $table = 'pharma_product_categories';
    public $timestamps = false;
    protected $fillable = ['category_name'];
    public function products() { return $this->hasMany(PharmaProduct::class, 'category_id'); }
}
