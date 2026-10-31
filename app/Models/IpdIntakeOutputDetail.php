<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ipd_intake_output_detail: id, ipd_intake_output_id, intake_output_time,
//   iv_fluids, oral, urine, stool, vomiting, rt_asp, drain
class IpdIntakeOutputDetail extends Model
{
    protected $table = 'ipd_intake_output_detail';
    public $timestamps = false;
    protected $fillable = [
        'ipd_intake_output_id', 'intake_output_time',
        'iv_fluids', 'oral', 'urine', 'stool', 'vomiting', 'rt_asp', 'drain',
    ];
}
