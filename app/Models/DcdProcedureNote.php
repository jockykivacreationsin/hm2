<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DcdProcedureNote extends Model
{
    protected $table = 'dcd_procedure_notes';
    public $timestamps = false;
    protected $fillable = ['dcd_service_id', 'note', 'updated_on', 'user_id'];
    public function service() { return $this->belongsTo(DcdService::class, 'dcd_service_id'); }
}
