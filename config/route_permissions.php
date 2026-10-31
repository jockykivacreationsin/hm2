<?php

/*
|--------------------------------------------------------------------------
| Module permission for every authenticated route
|--------------------------------------------------------------------------
| Key   = route name or name prefix (longest match wins; "ipd" covers "ipd.*").
| Value = permission keys, ANY ONE of which grants access.
|
| This is the baseline: it stops a role that only has one module (say m_ipd) from calling another
| module's AJAX/write endpoints directly. Finer b_* button checks stay in the controllers.
| Shared endpoints (pickers/autocompletes used by several screens) list every module that uses them.
| An authenticated route that matches nothing here is refused (fail-closed); a test enforces coverage.
*/

$clinical = ['m_opd', 'm_ipd', 'm_dcd'];

return [
    'dashboard' => ['m_dashboard'],

    'patients'              => ['m_patients'],
    // The "Edit Patient" modal on the OPD / DCD overview loads the record through this route.
    'patients.show'         => array_merge($clinical, ['m_patients']),
    'patients.autocomplete' => ['m_patients', 'm_patient_periodic_medicines'],

    'opd' => ['m_opd'],
    'ipd' => ['m_ipd'],
    'ipd.bed-picker' => ['m_ipd', 'm_dcd'],         // bed chooser modal is shared with the DCD screens
    'dcd' => ['m_dcd'],

    // Discharge menu (m_discharge) opens these list/detail pages and approves from them.
    'ipd.discharged'                => ['m_discharge', 'm_ipd'],
    'ipd.discharge-approvals'       => ['m_discharge', 'm_ipd'],
    'ipd.view-discharged'           => ['m_discharge', 'm_ipd'],
    'ipd.view-discharge-approval'   => ['m_discharge', 'm_ipd'],
    'ipd.discharge.approve'         => ['m_discharge', 'm_ipd'],
    'ipd.doctor'                    => ['m_discharge', 'm_ipd'],   // "Add doctor" modal on the discharge / approval pages
    'dcd.discharged'                => ['m_discharge', 'm_dcd'],
    'dcd.discharge-approvals'       => ['m_discharge', 'm_dcd'],
    'dcd.view-discharged'           => ['m_discharge', 'm_dcd'],
    'dcd.view-discharge-approval'   => ['m_discharge', 'm_dcd'],
    'dcd.discharge.approve'         => ['m_discharge', 'm_dcd'],

    'lab-results'           => ['m_opd', 'm_ipd', 'm_dcd'],   // opd/ipd/dcd results pages and the sub-test picker
    'lab-results.all'       => ['m_reports'],

    'billing' => ['m_billing'],
    'sale-bills' => ['m_sale_bill', 'im_sale_bill'],
    'rpt-sale-bills' => ['m_sale_bill'],
    'staff' => ['m_staff'],
    'beds' => ['m_beds'],

    'charges'              => ['m_charges'],
    'charges.autocomplete' => ['m_charges', 'm_opd', 'm_ipd', 'm_dcd'],
    // Department / category / charge pickers and the amount / prescription lookups used by the OPD, IPD, DCD and billing charge modals.
    'charges.lookup'       => array_merge($clinical, ['m_charges', 'm_billing', 'm_discharge', 'm_sale_bill']),

    // Module-neutral select2 sources (doctors, months) shared by many screens.
    'pickers' => array_merge($clinical, ['m_discharge', 'm_billing', 'm_staff', 'm_reports', 'im_collections', 'm_patient_periodic_medicines', 'm_sale_bill', 'm_charges']),

    'master'                          => ['m_master'],
    'master.pharmacy'                 => ['m_pharmacy'],
    'master.pharmacy-categories'      => ['m_pharmacy'],
    'master.pharmacy-category-products' => ['m_pharmacy', 'mi_pharmacy_products'],
    'master.pharmacy.search'          => ['m_pharmacy', 'm_opd', 'm_ipd', 'm_dcd'],
    'master.charge-categories'        => ['m_charges'],
    'master.charge-category-charges'  => ['m_charges'],
    'master.charges'                  => ['m_charges'],
    'master.sort-order'               => ['m_master', 'm_charges', 'm_beds'],   // the controller checks the permission of the table being reordered
    'master.diseases'                 => ['m_master', 'm_patient_periodic_medicines'],
    'master.disease-patients'         => ['m_master', 'm_patient_periodic_medicines'],
    'master.floor-areas'              => ['m_beds'],
    'master.hr-departments'           => ['m_staff'],
    'master.designations'             => ['m_staff'],

    // Photo cropper on the patient form (patients, OPD/IPD/DCD registration, periodic medicine) and the staff form.
    'uploads.photo'  => array_merge($clinical, ['m_patients', 'm_staff', 'm_patient_periodic_medicines']),

    'roles'          => ['m_roles_and_permissions'],
    'settings'       => ['m_roles_and_permissions'],
    'db-backups'     => ['m_roles_and_permissions'],
    'master.backups' => ['m_roles_and_permissions'],
    'audit-logs'     => ['im_audit_logs'],

    'reports'                        => ['m_reports'],
    'reports.collections'            => ['m_reports', 'im_collections'],
    'periodic-medicine'              => ['m_patient_periodic_medicines'],
    'periodic-medicine.issued'       => ['m_patient_periodic_medicines', 'm_master'],
    'periodic-medicine.issue-detail' => ['m_patient_periodic_medicines', 'm_master'],

    // PDFs are printed from the module screens and from billing/discharge.
    'pdf.opd'         => ['m_opd', 'm_billing', 'm_discharge'],
    'pdf.ipd'         => ['m_ipd', 'm_billing', 'm_discharge'],
    'pdf.dcd'         => ['m_dcd', 'm_billing', 'm_discharge'],
    'pdf.discharge'   => ['m_discharge', 'm_ipd', 'm_dcd'],
    'pdf.receipt'     => ['m_billing', 'm_sale_bill', ...$clinical],
    'pdf.collections' => ['m_reports', 'm_billing'],
    'pdf.sale-bills'  => ['m_sale_bill'],
    'pdf.staff-roster' => ['m_staff'],
    // Only the collections report writes to this disk (ReportController::createTablePdf, which needs m_reports), and the
    // file names are guessable timestamps, so it is not open to every module.
    'pdf.static-file' => ['m_reports', 'im_collections'],
    'reports.pdf.static-file' => ['m_reports', 'im_collections'],

    // ABDM: settings is admin-only; the account/consent pages follow the sidebar; the rest are called from
    // the patient form and the OPD/IPD/DCD screens (identity checks, care-context linking, HIU record views).
    'abdm'                        => [...$clinical, 'm_patients', 'im_opd_history'],
    'abdm.settings'               => ['m_roles_and_permissions'],
    'abdm.abha-account-services'  => ['im_opd_history'],
    'abdm.consent-requests'       => ['im_opd_history'],

    // Wellness records: index + CRUD scoped to OPD / IPD / DCD visits.
    'wellness'     => $clinical,
    'immunization' => $clinical,

    // Patient documents: per-module page routes (opd/ipd/dcd) + shared AJAX endpoints.
    'opd.patient-documents'          => ['m_opd'],
    'ipd.patient-documents'          => ['m_ipd'],
    'dcd.patient-documents'          => ['m_dcd'],
    'patient-documents'              => $clinical,
];
