<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// opd_lab_test_results: id, lab_investigation_id, opd_id, test_date_time,
//   opd_service_detail_id, test_id, test_value, highlighted
class OpdLabTestResult extends Model
{
    protected $table = 'opd_lab_test_results';
    public $timestamps = false;
    protected $fillable = [
        'lab_investigation_id', 'opd_id', 'test_date_time',
        'opd_service_detail_id', 'test_id', 'test_value', 'highlighted',
    ];
    public function subTest() { return $this->belongsTo(BiochemistrySubTest::class, 'test_id')->withDefault(); }
}
