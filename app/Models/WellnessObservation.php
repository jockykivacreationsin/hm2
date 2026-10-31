<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessObservation extends Model
{
    protected $table = 'wellness_observations';

    public $timestamps = false;

    protected $fillable = [
        'wellness_record_id',
        'type_id',
        'value_numeric',
        'value_text',
        'created_on',
    ];

    public function wellnessRecord()
    {
        return $this->belongsTo(WellnessRecord::class, 'wellness_record_id');
    }

    public function type()
    {
        return $this->belongsTo(WellnessObservationType::class, 'type_id')->withDefault();
    }
}
