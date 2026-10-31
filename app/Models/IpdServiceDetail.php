<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpdServiceDetail extends Model
{
    protected $table = 'ipd_service_detail';
    public $timestamps = false;
    protected $fillable = ['ipd_service_id', 'ipd_charge_detail_id', 'charge_id'];
    public function charge() { return $this->belongsTo(Charge::class)->withDefault(); }
}
