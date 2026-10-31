<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// dcd_lab_test_results: id, lab_investigation_id, dcd_id, test_date_time,
//   dcd_service_detail_id, test_id, test_value, highlighted
class DcdLabTestResult extends Model
{
    protected $table = 'dcd_lab_test_results';
    public $timestamps = false;
    protected $fillable = [
        'lab_investigation_id', 'dcd_id', 'test_date_time',
        'dcd_service_detail_id', 'test_id', 'test_value', 'highlighted',
    ];
    public function subTest() { return $this->belongsTo(BiochemistrySubTest::class, 'test_id')->withDefault(); }
}
