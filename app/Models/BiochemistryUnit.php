<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// bunits: id, unit_name, ucum_code
class BiochemistryUnit extends Model
{
    protected $table = 'bunits';
    public $timestamps = false;
    protected $fillable = ['unit_name', 'ucum_code'];
}
