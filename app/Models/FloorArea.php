<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// floor_areas: id, floor_id, area
class FloorArea extends Model
{
    protected $table = 'floor_areas';
    public $timestamps = false;
    protected $fillable = ['floor_id', 'area'];

    public function getFloorAreaAttribute()
    {
        return $this->attributes['area'] ?? null;
    }

    public function setFloorAreaAttribute($value)
    {
        $this->attributes['area'] = $value;
    }

    public function floor() { return $this->belongsTo(Floor::class); }
    public function beds()  { return $this->hasMany(Bed::class, 'floor_area_id'); }
}
