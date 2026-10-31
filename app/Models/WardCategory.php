<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// ward_categories: id, ward_category
class WardCategory extends Model
{
    protected $table = 'ward_categories';
    public $timestamps = false;
    protected $fillable = ['ward_category'];

    public function getWardCategoryNameAttribute()
    {
        return $this->attributes['ward_category'] ?? null;
    }

    public function setWardCategoryNameAttribute($value)
    {
        $this->attributes['ward_category'] = $value;
    }

    public function beds() { return $this->hasMany(Bed::class); }
}
