<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpdProcedureNote extends Model
{
    protected $table = 'ipd_procedure_notes';
    public $timestamps = false;
    protected $fillable = ['ipd_service_id', 'note', 'updated_on', 'user_id'];
}
