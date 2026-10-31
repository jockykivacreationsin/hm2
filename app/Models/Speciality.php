<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// specialities: id, speciality
class Speciality extends Model
{
    protected $table = 'specialities';
    public $timestamps = false;
    protected $fillable = ['speciality'];
}
