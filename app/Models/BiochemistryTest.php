<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// biochemistry_tests_result: id, lab_investigation_id, ipd_id,
//   test_date_time, ipd_service_detail_id, test_id, test_value, highlighted
class BiochemistryTest extends Model
{
    protected $table = 'biochemistry_tests_result';
    public $timestamps = false;
    protected $fillable = [
        'lab_investigation_id', 'ipd_id', 'test_date_time',
        'ipd_service_detail_id', 'test_id', 'test_value', 'highlighted',
    ];
    public function subTest() { return $this->belongsTo(BiochemistrySubTest::class, 'test_id')->withDefault(); }
}
