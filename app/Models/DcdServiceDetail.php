<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdServiceDetail extends Model
{
    protected $table = 'dcd_service_detail';
    public $timestamps = false;
    protected $fillable = ['dcd_service_id', 'dcd_charge_detail_id', 'charge_id'];

    public function charge() { return $this->belongsTo(Charge::class)->withDefault(); }
    public function service() { return $this->belongsTo(DcdService::class, 'dcd_service_id'); }
}
