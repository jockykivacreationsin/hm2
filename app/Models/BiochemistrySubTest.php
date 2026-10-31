<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// biochemistry_sub_tests: id, main_category_id, sub_test_name, reference_range, unit_id
class BiochemistrySubTest extends Model
{
    protected $table = 'biochemistry_sub_tests';
    public $timestamps = false;
    protected $fillable = ['main_category_id', 'sub_test_name', 'reference_range', 'unit_id'];
    public function unit() { return $this->belongsTo(BiochemistryUnit::class, 'unit_id')->withDefault(); }
}
