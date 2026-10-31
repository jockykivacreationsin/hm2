<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OpdServiceDetail extends Model
{
    protected $table = 'opd_service_detail';
    public $timestamps = false;
    protected $fillable = ['opd_service_id', 'opd_charge_detail_id', 'charge_id'];
    public function charge() { return $this->belongsTo(Charge::class)->withDefault(); }
}
