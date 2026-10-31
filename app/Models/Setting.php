<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// settings: id, hospital_name, address, phone_number, mobile_number, contact_number,
//   email, slogan_general, slogan_hospital, logo, square_logo,
//   bmh_billing_start_number, ivy_billing_start_number, signer, uhid_prefix, uhid_suffix, uhid_padding,
//   abdm_client_id, abdm_client_secret, facility_name, facility_hfr_id,
//   abdm_hiu_id, abdm_bridge_callback_base_url, abha_creation_terms, admin_password
class Setting extends Model
{
    protected $table = 'settings';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden  = ['abdm_client_secret', 'admin_password'];
}
