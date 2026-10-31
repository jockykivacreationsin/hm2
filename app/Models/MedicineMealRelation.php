<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineMealRelation extends Model
{
    protected $table = 'medicine_meal_relation';

    public $timestamps = false;

    protected $fillable = [
        'relation_name',
        'relation_code',
        'relation_display',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
