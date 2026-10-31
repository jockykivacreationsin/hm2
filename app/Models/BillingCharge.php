<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCharge extends Model
{
    protected $table = 'billing_charges';

    public $timestamps = false;

    protected $fillable = [
        'invoice_number',
        'invoice_number_two',
        'charge_date_time',
        'module',
        'module_id',
        'charge_title',
        'charge_department_id',
        'amount',
        'discount',
        'addon_discount_mode',
        'addon_discount_value',
        'addon_discount_amount',
        'total',
        'paid_amount',
        'payment_mode',
        'invoice_status',
        'is_hidden',
        'created_on',
        'user_id',
    ];

    /**
     * Charges of one clinical module only ('OPD', 'IPD' or 'DCD'). billing_charges is shared by all three (and by manual
     * bills, which store the patient name in `module`), so a screen that loads a charge by id alone would let a user who
     * only has one module read, change or delete another module's bill by editing the id in the request.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function details()
    {
        return $this->hasMany(BillingChargeDetail::class, 'module_charge_id');
    }

    public function payments()
    {
        return $this->hasMany(BillingChargePayment::class, 'billing_charge_id');
    }

    public function chargeDepartment()
    {
        return $this->belongsTo(ChargeDepartment::class, 'charge_department_id');
    }
}
