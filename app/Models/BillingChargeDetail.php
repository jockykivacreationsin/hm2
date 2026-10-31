<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingChargeDetail extends Model
{
    protected $table = 'billing_charges_detail';

    public $timestamps = false;

    protected $fillable = [
        'module_charge_id',
        'charge_id',
        'quantity',
        'amount',
        'discount_percentage',
        'discount_amount',
        'total',
        'tax_percentage',
        'tax_amount',
    ];

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }
}
