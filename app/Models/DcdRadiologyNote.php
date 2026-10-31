<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DcdRadiologyNote extends Model
{
    protected $table = 'dcd_radiology_notes';
    public $timestamps = false;
    protected $fillable = ['dcd_service_id', 'note', 'updated_on', 'user_id'];

    public function staff()
    {
        return $this->belongsTo(StaffMember::class, 'user_id')->withDefault();
    }
}
