<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// floors: id, floor, sort_order
class Floor extends Model
{
    protected $table = 'floors';
    public $timestamps = false;
    protected $fillable = ['floor', 'sort_order'];

    public function getFloorNameAttribute()
    {
        return $this->attributes['floor'] ?? null;
    }

    public function setFloorNameAttribute($value)
    {
        $this->attributes['floor'] = $value;
    }

    public function areas() { return $this->hasMany(FloorArea::class, 'floor_id'); }
    public function beds()  { return $this->hasMany(Bed::class, 'floor_id'); }
}
