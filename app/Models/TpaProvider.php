<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// tpa_providers: id, tpa_name, contact_person, address, contact_number, email
class TpaProvider extends Model
{
    protected $table = 'tpa_providers';
    public $timestamps = false;
    protected $fillable = ['tpa_name', 'contact_person', 'address', 'contact_number', 'email'];
}
