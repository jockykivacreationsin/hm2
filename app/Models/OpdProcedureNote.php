<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OpdProcedureNote extends Model
{
    protected $table = 'opd_procedure_notes';
    public $timestamps = false;
    protected $fillable = ['opd_service_id', 'note', 'updated_on', 'user_id'];
}
