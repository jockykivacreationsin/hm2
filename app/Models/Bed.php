<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $table = 'beds';

    public $timestamps = false;

    protected $fillable = [
        'bed_name',
        'floor_id',
        'floor_area_id',
        'ward_category_id',
        'bed_type_id',
        'status',
        'sort_order',
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    public function floorArea()
    {
        return $this->belongsTo(FloorArea::class, 'floor_area_id');
    }

    public function wardCategory()
    {
        return $this->belongsTo(WardCategory::class, 'ward_category_id');
    }

    public function bedType()
    {
        return $this->belongsTo(BedType::class, 'bed_type_id');
    }
}
