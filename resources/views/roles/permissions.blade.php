@extends('layouts.app')
@section('title', 'Role Permissions')
@section('content')

@if(!$canAccess)
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-header permissions-h"> 
            <h4>{{ $role->role }} Access Permissions</h4> 
            <label class="checkbox-label"><input id="checkAll" type="checkbox"><span>All</span></label>	
        </div>
        <div class="card-body">
            <form id="save_role_permissions_form" action="{{ route('roles.permissions.update', ['id' => $role->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="rp-box row">
                    <div class="col-md-4">
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_dashboard" @checked(in_array('m_dashboard', $assignedPermissions))><span>Dashboard</span></label>
                        </div>
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_top" @checked(in_array('m_top', $assignedPermissions))><span>Top Menu</span></label>	
                            <div class="level-2">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_beds_ui" @checked(in_array('b_beds_ui', $assignedPermissions))><span>Beds UI</span></label>
                            </div>
                        </div>
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_sale_bill" @checked(in_array('m_sale_bill', $assignedPermissions))><span>Sale</span></label>	
                            <div class="level-2">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_sale_bill" @checked(in_array('im_sale_bill', $assignedPermissions))><span>Bills</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_sale_search" @checked(in_array('im_sale_search', $assignedPermissions))><span>Search</span></label>	
                            </div>
                        </div>
                    </div>
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_opd" @checked(in_array('m_opd', $assignedPermissions))><span>OPD</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_new_opd" @checked(in_array('b_new_opd', $assignedPermissions))><span>New OPD</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_overview" @checked(in_array('im_opd_overview', $assignedPermissions))><span>OPD Overview</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_opd_save" @checked(in_array('b_opd_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_charges" @checked(in_array('im_opd_charges', $assignedPermissions))><span>Charges</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_opd_new_charge" @checked(in_array('b_opd_new_charge', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_opd_edit_charge" @checked(in_array('b_opd_edit_charge', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_opd_delete_charge" @checked(in_array('b_opd_delete_charge', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_prescription" @checked(in_array('im_opd_prescription', $assignedPermissions))><span>Prescription</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_new_opd_prescription" @checked(in_array('b_new_opd_prescription', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_edit_opd_prescription" @checked(in_array('b_edit_opd_prescription', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_delete_opd_prescription" @checked(in_array('b_delete_opd_prescription', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_history" @checked(in_array('im_opd_history', $assignedPermissions))><span>History</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_opd_view_history_detail" @checked(in_array('b_opd_view_history_detail', $assignedPermissions))><span>View Detail</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_lab_investigations" @checked(in_array('im_opd_lab_investigations', $assignedPermissions))><span>Lab Investigations</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_procedures" @checked(in_array('im_opd_procedures', $assignedPermissions))><span>Procedures</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_wellness" @checked(in_array('im_opd_wellness', $assignedPermissions))><span>Wellness Records</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_save" @checked(in_array('b_wellness_save', $assignedPermissions))><span>Save</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_delete" @checked(in_array('b_wellness_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_opd_immunization" @checked(in_array('im_opd_immunization', $assignedPermissions))><span>Immunization</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_add" @checked(in_array('b_immunization_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_edit" @checked(in_array('b_immunization_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_delete" @checked(in_array('b_immunization_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_ipd" @checked(in_array('m_ipd', $assignedPermissions))><span>IPD</span></label>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_new_ipd" @checked(in_array('b_new_ipd', $assignedPermissions))><span>New IPD</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_overview" @checked(in_array('im_ipd_overview', $assignedPermissions))><span>IPD Overview</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_change_bed" @checked(in_array('b_ipd_change_bed', $assignedPermissions))><span>Change Bed</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_discharge_patient" @checked(in_array('b_ipd_discharge_patient', $assignedPermissions))><span>Discharge Patient</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_save" @checked(in_array('b_ipd_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_charges" @checked(in_array('im_ipd_charges', $assignedPermissions))><span>Charges</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_invoice" @checked(in_array('b_ipd_invoice', $assignedPermissions))><span>Invoice</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_new_charge" @checked(in_array('b_ipd_new_charge', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_edit_charge" @checked(in_array('b_ipd_edit_charge', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_delete_charge" @checked(in_array('b_ipd_delete_charge', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_payments" @checked(in_array('im_ipd_payments', $assignedPermissions))><span>Payments</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_add_payment" @checked(in_array('b_ipd_add_payment', $assignedPermissions))><span>Add Payment</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_prescription" @checked(in_array('im_ipd_prescription', $assignedPermissions))><span>Prescription</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_new_prescription" @checked(in_array('b_ipd_new_prescription', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_edit_prescription" @checked(in_array('b_ipd_edit_prescription', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_delete_prescription" @checked(in_array('b_ipd_delete_prescription', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_history" @checked(in_array('im_ipd_history', $assignedPermissions))><span>History</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_view_history_detail" @checked(in_array('b_ipd_view_history_detail', $assignedPermissions))><span>View</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_medication" @checked(in_array('im_ipd_medication', $assignedPermissions))><span>Medication</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_copy_medication" @checked(in_array('b_ipd_copy_medication', $assignedPermissions))><span>Copy</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_lab_investigations" @checked(in_array('im_ipd_lab_investigations', $assignedPermissions))><span>Lab Investigations</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_procedures" @checked(in_array('im_ipd_procedures', $assignedPermissions))><span>Procedures</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_consultations" @checked(in_array('im_ipd_consultations', $assignedPermissions))><span>Consultations</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_consultation_add" @checked(in_array('b_ipd_consultation_add', $assignedPermissions))><span>Add</span></label>	
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_consultation_edit" @checked(in_array('b_ipd_consultation_edit', $assignedPermissions))><span>Edit</span></label>	
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_consultation_delete" @checked(in_array('b_ipd_consultation_delete', $assignedPermissions))><span>Delete</span></label>	
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_bed_history" @checked(in_array('im_ipd_bed_history', $assignedPermissions))><span>Bed History</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_wellness" @checked(in_array('im_ipd_wellness', $assignedPermissions))><span>Wellness Records</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_save" @checked(in_array('b_wellness_save', $assignedPermissions))><span>Save</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_delete" @checked(in_array('b_wellness_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_immunization" @checked(in_array('im_ipd_immunization', $assignedPermissions))><span>Immunization</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_add" @checked(in_array('b_immunization_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_edit" @checked(in_array('b_immunization_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_delete" @checked(in_array('b_immunization_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_dcd" @checked(in_array('m_dcd', $assignedPermissions))><span>DC</span></label>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_new_dcd" @checked(in_array('b_new_dcd', $assignedPermissions))><span>New DC</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_overview" @checked(in_array('im_dcd_overview', $assignedPermissions))><span>DC Overview</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_discharge_patient" @checked(in_array('b_dcd_discharge_patient', $assignedPermissions))><span>Discharge Patient</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_to_ipd" @checked(in_array('b_dcd_to_ipd', $assignedPermissions))><span>Shift to IPD</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_save" @checked(in_array('b_dcd_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_file" @checked(in_array('im_dcd_file', $assignedPermissions))><span>File</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_file_save" @checked(in_array('b_dcd_file_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_charges" @checked(in_array('im_dcd_charges', $assignedPermissions))><span>Charges</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_invoice" @checked(in_array('b_dcd_invoice', $assignedPermissions))><span>Invoice</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_new_charge" @checked(in_array('b_dcd_new_charge', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_edit_charge" @checked(in_array('b_dcd_edit_charge', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_delete_charge" @checked(in_array('b_dcd_delete_charge', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_payments" @checked(in_array('im_dcd_payments', $assignedPermissions))><span>Payments</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_prescription" @checked(in_array('im_dcd_prescription', $assignedPermissions))><span>Prescription</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_new_prescription" @checked(in_array('b_dcd_new_prescription', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_edit_prescription" @checked(in_array('b_dcd_edit_prescription', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_delete_prescription" @checked(in_array('b_dcd_delete_prescription', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_history" @checked(in_array('im_dcd_history', $assignedPermissions))><span>History</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_view_history_detail" @checked(in_array('b_dcd_view_history_detail', $assignedPermissions))><span>View</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_medication" @checked(in_array('im_dcd_medication', $assignedPermissions))><span>Medication</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_lab_investigations" @checked(in_array('im_dcd_lab_investigations', $assignedPermissions))><span>Lab Investigations</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_procedures" @checked(in_array('im_dcd_procedures', $assignedPermissions))><span>Procedures</span></label>
                            <div class="level-3"></div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_wellness" @checked(in_array('im_dcd_wellness', $assignedPermissions))><span>Wellness Records</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_save" @checked(in_array('b_wellness_save', $assignedPermissions))><span>Save</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_wellness_delete" @checked(in_array('b_wellness_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_immunization" @checked(in_array('im_dcd_immunization', $assignedPermissions))><span>Immunization</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_add" @checked(in_array('b_immunization_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_edit" @checked(in_array('b_immunization_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_immunization_delete" @checked(in_array('b_immunization_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_billing" @checked(in_array('m_billing', $assignedPermissions))><span>Billing</span></label>
                            <div class="level-2">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_billing_new" @checked(in_array('b_billing_new', $assignedPermissions))><span>New Bill</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_billing_edit" @checked(in_array('b_billing_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_billing_delete" @checked(in_array('b_billing_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_patients" @checked(in_array('m_patients', $assignedPermissions)) disabled><span>Patient Documents</span></label>
                            <div class="level-2">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_patient_documents" @checked(in_array('im_patient_documents', $assignedPermissions))><span>View Documents</span></label>
                                <div class="level-3">
                                    <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_document_upload" @checked(in_array('b_document_upload', $assignedPermissions))><span>Upload</span></label>
                                    <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_document_delete" @checked(in_array('b_document_delete', $assignedPermissions))><span>Delete</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="level-1">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_patients" @checked(in_array('m_patients', $assignedPermissions))><span>Patients</span></label>	
                            <div class="level-2">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_patient_new" @checked(in_array('b_patient_new', $assignedPermissions))><span>New Patient</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_patient_edit" @checked(in_array('b_patient_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_patient_visit_history" @checked(in_array('b_patient_visit_history', $assignedPermissions))><span>Visit History</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_patient_file" @checked(in_array('m_patient_file', $assignedPermissions))><span>File Management</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_patient_file" @checked(in_array('im_ipd_patient_file', $assignedPermissions))><span>Patient's File</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_patient_file_save" @checked(in_array('b_ipd_patient_file_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_treatment" @checked(in_array('im_ipd_treatment', $assignedPermissions))><span>Treatment</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_new_treatment" @checked(in_array('b_ipd_new_treatment', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_edit_treatment" @checked(in_array('b_ipd_edit_treatment', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_delete_treatment" @checked(in_array('b_ipd_delete_treatment', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_vital_monitoring" @checked(in_array('im_ipd_vital_monitoring', $assignedPermissions))><span>Nursing Care</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_new_vital_monitoring" @checked(in_array('b_ipd_new_vital_monitoring', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_edit_vital_monitoring" @checked(in_array('b_ipd_edit_vital_monitoring', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_delete_vital_monitoring" @checked(in_array('b_ipd_delete_vital_monitoring', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_intake_output" @checked(in_array('im_ipd_intake_output', $assignedPermissions))><span>Intake Output</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_new_intake_output" @checked(in_array('b_ipd_new_intake_output', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_edit_intake_output" @checked(in_array('b_ipd_edit_intake_output', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_delete_intake_output" @checked(in_array('b_ipd_delete_intake_output', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_progress_sheet" @checked(in_array('im_ipd_progress_sheet', $assignedPermissions))><span>Progress Report</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_progress_sheet_save" @checked(in_array('b_ipd_progress_sheet_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ipd_rmo_notes" @checked(in_array('im_ipd_rmo_notes', $assignedPermissions))><span>RMO Notes</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ipd_rmo_notes_save" @checked(in_array('b_ipd_rmo_notes_save', $assignedPermissions))><span>Save</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_staff" @checked(in_array('m_staff', $assignedPermissions))><span>Staff</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_staff_new" @checked(in_array('b_staff_new', $assignedPermissions))><span>New</span></label>
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_staff_edit" @checked(in_array('b_staff_edit', $assignedPermissions))><span>Edit</span></label>
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_staff_delete" @checked(in_array('b_staff_delete', $assignedPermissions))><span>Delete</span></label>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_pharmacy" @checked(in_array('m_pharmacy', $assignedPermissions))><span>Pharmacy</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_new" @checked(in_array('b_pharma_category_new', $assignedPermissions))><span>New</span></label>
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_product_list" @checked(in_array('b_pharma_category_product_list', $assignedPermissions))><span>Products</span></label>
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_edit" @checked(in_array('b_pharma_category_edit', $assignedPermissions))><span>Edit</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="mi_pharmacy_products" @checked(in_array('mi_pharmacy_products', $assignedPermissions))><span>Pharmacy Products</span></label>	
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_product_new" @checked(in_array('b_pharma_category_product_new', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_product_edit" @checked(in_array('b_pharma_category_product_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_pharma_category_product_delete" @checked(in_array('b_pharma_category_product_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_beds" @checked(in_array('m_beds', $assignedPermissions))><span>Beds</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_floors" @checked(in_array('im_floors', $assignedPermissions))><span>Floors</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_floors_add" @checked(in_array('b_floors_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_floors_edit" @checked(in_array('b_floors_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_floors_areas" @checked(in_array('b_floors_areas', $assignedPermissions))><span>Floor Areas</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_floor_areas" @checked(in_array('im_floor_areas', $assignedPermissions))><span>Floor Areas</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_floor_areas_add" @checked(in_array('b_floor_areas_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_floor_areas_edit" @checked(in_array('b_floor_areas_edit', $assignedPermissions))><span>Edit</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_ward_categories" @checked(in_array('im_ward_categories', $assignedPermissions))><span>Ward Categories</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ward_categories_add" @checked(in_array('b_ward_categories_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_ward_categories_edit" @checked(in_array('b_ward_categories_edit', $assignedPermissions))><span>Edit</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_beds" @checked(in_array('im_beds', $assignedPermissions))><span>Beds</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_beds_add" @checked(in_array('b_beds_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_beds_edit" @checked(in_array('b_beds_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_beds_copy" @checked(in_array('b_beds_copy', $assignedPermissions))><span>Copy</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_charges" @checked(in_array('m_charges', $assignedPermissions))><span>Charges</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_charges_departments" @checked(in_array('im_charges_departments', $assignedPermissions))><span>Departments</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_departments_categories_list" @checked(in_array('b_departments_categories_list', $assignedPermissions))><span>Categories</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_charges_categories" @checked(in_array('im_charges_categories', $assignedPermissions))><span>Categories</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_categories_new" @checked(in_array('b_charges_categories_new', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_categories_edit" @checked(in_array('b_charges_categories_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_categories_charges" @checked(in_array('b_charges_categories_charges', $assignedPermissions))><span>Charges</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_charges" @checked(in_array('im_charges', $assignedPermissions))><span>Charges</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_new" @checked(in_array('b_charges_new', $assignedPermissions))><span>New</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_edit" @checked(in_array('b_charges_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_charges_delete" @checked(in_array('b_charges_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_discharge" @checked(in_array('m_discharge', $assignedPermissions))><span>Discharges</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_discharge_approval" @checked(in_array('im_discharge_approval', $assignedPermissions))><span>Approval</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_discharge_approval_view" @checked(in_array('b_discharge_approval_view', $assignedPermissions))><span>View Approval</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_discharge_approve" @checked(in_array('im_discharge_approve', $assignedPermissions))><span>Discharge Approve</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_discharge_approve_and_discharge" @checked(in_array('b_discharge_approve_and_discharge', $assignedPermissions))><span>Approve &amp; Discharge</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_discharge_patients" @checked(in_array('im_discharge_patients', $assignedPermissions))><span>Discharged Patients</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_discharge_patient_view" @checked(in_array('b_discharge_patient_view', $assignedPermissions))><span>View</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_discharge_approval" @checked(in_array('im_dcd_discharge_approval', $assignedPermissions))><span>DC Approval</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_discharge_approval_view" @checked(in_array('b_dcd_discharge_approval_view', $assignedPermissions))><span>DC View Approval</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_discharge_approve" @checked(in_array('im_dcd_discharge_approve', $assignedPermissions))><span>DC Discharge Approve</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_discharge_approve_and_discharge" @checked(in_array('b_dcd_discharge_approve_and_discharge', $assignedPermissions))><span>DC Approve &amp; Discharge</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_dcd_discharge_patients" @checked(in_array('im_dcd_discharge_patients', $assignedPermissions))><span>DC Discharged Patients</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_dcd_discharge_patient_view" @checked(in_array('b_dcd_discharge_patient_view', $assignedPermissions))><span>DC View</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_master" @checked(in_array('m_master', $assignedPermissions))><span>Master</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_tpa" @checked(in_array('im_tpa', $assignedPermissions))><span>TPA</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_tpa_add" @checked(in_array('b_tpa_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_tpa_edit" @checked(in_array('b_tpa_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_tpa_delete" @checked(in_array('b_tpa_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_bunits" @checked(in_array('im_bunits', $assignedPermissions))><span>Biochemistry Units</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_bunits_add" @checked(in_array('b_bunits_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_bunits_edit" @checked(in_array('b_bunits_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_bunits_delete" @checked(in_array('b_bunits_delete', $assignedPermissions))><span>Delete</span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_reports" @checked(in_array('m_reports', $assignedPermissions))><span>Reports</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_collections" @checked(in_array('im_collections', $assignedPermissions))><span>Collections</span></label>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_patient_periodic_medicines" @checked(in_array('m_patient_periodic_medicines', $assignedPermissions))><span>Patient Medicines</span></label>	
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_diseases" @checked(in_array('im_diseases', $assignedPermissions))><span>Diseases</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_diseases_add" @checked(in_array('b_diseases_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_diseases_edit" @checked(in_array('b_diseases_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_disease_patients" @checked(in_array('b_disease_patients', $assignedPermissions))><span>Patients</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_patient_medicine_issue" @checked(in_array('im_patient_medicine_issue', $assignedPermissions))><span>Issue Medicine</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_add" @checked(in_array('b_add', $assignedPermissions))><span>New</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_periodic_medicine_patients" @checked(in_array('im_periodic_medicine_patients', $assignedPermissions))><span>Patients</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_disease_patients" @checked(in_array('im_disease_patients', $assignedPermissions))><span>Disease Patients</span></label>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_patients_medicine_due_days" @checked(in_array('im_patients_medicine_due_days', $assignedPermissions))><span>Medicine Due Days</span></label>
                        </div>
                    </div>
                    
                    <div class="level-1 col-md-4">
                        <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="m_roles_and_permissions" @checked(in_array('m_roles_and_permissions', $assignedPermissions))><span>Roles &amp; Permissions</span></label>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_roles" @checked(in_array('im_roles', $assignedPermissions))><span>Roles</span></label>
                            <div class="level-3">
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_role_add" @checked(in_array('b_role_add', $assignedPermissions))><span>Add</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_role_edit" @checked(in_array('b_role_edit', $assignedPermissions))><span>Edit</span></label>
                                <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="b_role_permissions" @checked(in_array('b_role_permissions', $assignedPermissions))><span>Permissions</span></label>
                            </div>
                        </div>
                        <div class="level-2">
                            <label class="checkbox-label"><input name="permissions[]" type="checkbox" value="im_audit_logs" @checked(in_array('im_audit_logs', $assignedPermissions))><span>Audit Log <span style="font-size:10px;color:#6b7280;">(PHI / HDMP §9)</span></span></label>
                        </div>
                    </div>
                </div>
                <div class="bottom-center-btns mt-3">
                    <button class="btn btn-success btn-rounded" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if($canAccess && $role)
<script>
$(document).ready(function() {
    $("#checkAll").on('click', function(){
        $('input:checkbox').not(this).prop('checked', this.checked);
    });

    $("#save_role_permissions_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: this.action,
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "Saved Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error saving permissions.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
});
</script>
@endif
@endpush
