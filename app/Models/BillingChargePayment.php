<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingChargePayment extends Model
{
    protected $table = 'billing_charges_payments';

    public $timestamps = false;

    protected $fillable = [
        'billing_charge_id',
        'payment_date_time',
        'payment_mode',
        'amount',
    ];
}
