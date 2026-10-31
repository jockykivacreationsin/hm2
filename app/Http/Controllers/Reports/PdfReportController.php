<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\OpdPrescription;
use App\Models\OpdMedicine;
use App\Models\OpdService;
use App\Models\OpdLabTestResult;
use App\Models\Ipd;
use App\Models\IpdPrescription;
use App\Models\IpdMedicine;
use App\Models\IpdDischarge;
use App\Models\IpdPatientFile;
use App\Models\IpdService;
use App\Models\IpdTreatment;
use App\Models\IpdVitalMonitoring;
use App\Models\IpdIntakeOutput;
use App\Models\Dcd;
use App\Models\DcdPrescription;
use App\Models\DcdMedicine;
use App\Models\DcdDischarge;
use App\Models\DcdService;
use App\Models\DcdLabTestResult;
use App\Models\BiochemistryTest;
use App\Models\BillingCharge;
use App\Models\BillingChargeDetail;
use App\Models\Setting;

use App\Services\Pdf\LegacyReportRenderer;
use App\Services\Pdf\IpdReportData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PdfReportController extends Controller
{
    public function __construct(
        private IpdReportData $ipdData,
    ) {}

    // ─────────────────────────────────────────────────────────
    // OPD Reports
    // ─────────────────────────────────────────────────────────

    /** OPD Card PDF (compact patient card with token + prescriptions) */
    public function opdCard(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd = Opd::with(['patient', 'doctor', 'prescriptions.medicines.product'])->findOrFail($opdId);

        return $this->standardPdfResponse($renderer, 'pdf.opd-card', 'OPD_Card_' . $opdId, compact('opd'));
    }

    /** IPD Discharge card by discharge_id (ipd_discharge.id) */
    public function ipdDischargeCard(int $dischargeId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $discharge = \Illuminate\Support\Facades\DB::table('ipd_discharge')->where('id', $dischargeId)->first();
        if (!$discharge) abort(404);

        return $this->ipdDischargeSummary((int) $discharge->ipd_id, $renderer);
    }

    /** DCD Discharge card by discharge_id (dcd_discharge.id) */
    public function dcdDischargeCard(int $dischargeId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $discharge = \Illuminate\Support\Facades\DB::table('dcd_discharge')->where('id', $dischargeId)->first();
        if (!$discharge) abort(404);

        return $this->dcdDischargeSummary((int) $discharge->dcd_id, $renderer);
    }

    /**
     * OPD registration-fee receipt (legacy rpt-opd-receipt.php): half-page (A5) on the standard layout.
     * Linked from the "Receipt" icon of the OPD list.
     */
    public function opdReceipt(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd = Opd::with(['patient'])->findOrFail($opdId);

        return $this->standardPdfResponse(
            $renderer, 'pdf.opd-receipt', 'OPD_Receipt_' . $opdId, compact('opd'),
            ['format' => LegacyReportRenderer::HALF]
        );
    }

    /**
     * OPD card slip (legacy rpt-receipt.php): half-page header with the patient details, rest of the page blank.
     * Linked from the "Card Slip" icon of the OPD list.
     */
    public function opdCardSlip(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd = Opd::with(['patient'])->findOrFail($opdId);

        return $this->standardPdfResponse(
            $renderer, 'pdf.opd-card-slip', 'OPD_Card_Slip_' . $opdId, ['opd' => $opd, 'hideSignature' => true],
            ['format' => LegacyReportRenderer::HALF]
        );
    }

    /** OPD Prescription PDF */
    public function opdPrescription(int $opdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $rxId = $request->integer('prescription_id');

        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $query = OpdPrescription::with(['doctor', 'medicines.product.doseUnit', 'medicines.mealRelation', 'medicines.timings.timing'])
            ->where('opd_id', $opdId);
        if ($rxId) $query->where('id', $rxId);
        $prescriptions = $query->orderBy('prescription_date_time')->get();

        $chargeNames = $this->prescriptionChargeNames($prescriptions);

        return $this->standardPdfResponse($renderer, 'pdf.opd-prescription', 'OPD_Prescription_' . $opdId, compact('opd', 'prescriptions', 'chargeNames'));
    }

    /** pathology_tests / radiology_tests / procedures hold comma-separated charge ids; returns [id => charge_name]. */
    private function prescriptionChargeNames($prescriptions)
    {
        $chargeIds = $prescriptions
            ->flatMap(fn ($rx) => [$rx->pathology_tests, $rx->radiology_tests, $rx->procedures])
            ->flatMap(fn ($v) => explode(',', (string) $v))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values();

        return \App\Models\Charge::whereIn('id', $chargeIds)->pluck('charge_name', 'id');
    }

    /** OPD Bill PDF (single billing_charge record) */
    public function opdBill(int $chargeId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $charge = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])->findOrFail($chargeId);
        $opd    = Opd::with(['patient'])->findOrFail($charge->module_id);

        return $this->standardPdfResponse($renderer, 'pdf.bill', 'Bill_' . $chargeId, compact('charge', 'opd'));
    }

    /** OPD Visit Summary (all charges + prescriptions for one visit) */
    public function opdVisitSummary(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $prescriptions = OpdPrescription::with(['doctor', 'medicines.product.doseUnit', 'medicines.mealRelation', 'medicines.timings.timing'])->where('opd_id', $opdId)->get();
        $services      = OpdService::with(['chargeDepartment', 'details.charge', 'billingCharge'])->where('opd_id', $opdId)->get();
        $chargeNames   = $this->prescriptionChargeNames($prescriptions);

        return $this->standardPdfResponse($renderer, 'pdf.opd-visit-summary', 'OPD_Visit_Summary_' . $opdId, compact('opd', 'prescriptions', 'services', 'chargeNames'));
    }

    /** OPD Consolidated Bill (all charges for a visit) */
    public function opdConsolidatedBill(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd     = Opd::with(['patient'])->findOrFail($opdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'OPD')->where('module_id', $opdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.consolidated-bill', 'OPD_Bill_' . $opdId, compact('opd', 'charges'));
    }

    // ─────────────────────────────────────────────────────────
    // IPD Reports
    // ─────────────────────────────────────────────────────────

    /** IPD Prescription PDF */
    public function ipdPrescription(int $ipdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $rxId = $request->integer('prescription_id');
        $d = $this->ipdData->prescription($ipdId, $rxId ?: null);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-prescription', 'IPD_Prescription_' . $ipdId, compact('d'));
    }

    /** IPD Discharge Summary */
    public function ipdDischargeSummary(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $ipd = Ipd::with([
            'patient', 'doctor', 'bed.wardCategory', 'bed.floor',
            'discharge', 'tpaProvider', 'prescriptions.doctor', 'prescriptions.medicines.product',
            'services.details.charge', 'services.chargeDepartment', 'services.billingCharge',
            'vitalMonitorings.details',
        ])->findOrFail($ipdId);

        return $this->standardPdfResponse($renderer, 'pdf.ipd-discharge-summary', 'IPD_Discharge_Summary_' . $ipdId, compact('ipd'));
    }

    /** IPD Consolidated Bill */
    public function ipdConsolidatedBill(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $ipd     = Ipd::with(['patient', 'tpaProvider'])->findOrFail($ipdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'IPD')->where('module_id', $ipdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.consolidated-bill', 'IPD_Bill_' . $ipdId, compact('ipd', 'charges'));
    }

    /** IPD Discharge Bill (discharge summary + all charges) */
    public function ipdDischargeBill(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $d = $this->ipdData->dischargeBill($ipdId);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-discharge-bill', 'IPD_Discharge_Bill_' . $ipdId, compact('d'));
    }

    // ─────────────────────────────────────────────────────────
    // DCD Reports
    // ─────────────────────────────────────────────────────────

    /** DCD Prescription PDF */
    public function dcdPrescription(int $dcdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $rxId = $request->integer('prescription_id');
        $dcd  = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $query = DcdPrescription::with(['doctor', 'medicines.product'])->where('dcd_id', $dcdId);
        if ($rxId) $query->where('id', $rxId);
        $prescriptions = $query->orderBy('prescription_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.dcd-prescription', 'DCD_Prescription_' . $dcdId, compact('dcd', 'prescriptions'));
    }

    /** DCD Discharge Summary */
    public function dcdDischargeSummary(int $dcdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $dcd = Dcd::with([
            'patient', 'doctor', 'bed.wardCategory',
            'discharge', 'prescriptions.doctor', 'prescriptions.medicines.product',
        ])->findOrFail($dcdId);

        return $this->standardPdfResponse($renderer, 'pdf.dcd-discharge-summary', 'DCD_Discharge_Summary_' . $dcdId, compact('dcd'));
    }

    /** DCD Consolidated Bill */
    public function dcdConsolidatedBill(int $dcdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $dcd     = Dcd::with(['patient'])->findOrFail($dcdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'DCD')->where('module_id', $dcdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.consolidated-bill', 'DCD_Bill_' . $dcdId, compact('dcd', 'charges'));
    }

    /** IPD Detailed Discharge Bill (itemised per charge with sub-items) */
    public function ipdDischargeDetailedBill(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $d = $this->ipdData->dischargeDetailedBill($ipdId);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-discharge-detailed-bill', 'IPD_Discharge_Detailed_Bill_' . $ipdId, compact('d'));
    }

    /** DCD Detailed Discharge Bill */
    public function dcdDischargeDetailedBill(int $dcdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $dcd     = Dcd::with(['patient', 'doctor', 'bed.wardCategory', 'discharge'])->findOrFail($dcdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'DCD')->where('module_id', $dcdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.dcd-discharge-detailed-bill', 'DCD_Discharge_Detailed_Bill_' . $dcdId, compact('dcd', 'charges'));
    }

    // ────────────────────────────────────────────────────────
    // Lab Investigation PDF Reports
    // ────────────────────────────────────────────────────────

    /** OPD Lab Investigation Report */
    public function opdLabReport(int $opdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);

        $services = $this->labServices(
            OpdService::query()->where('opd_id', $opdId),
            OpdLabTestResult::with('subTest.unit')->where('opd_id', $opdId)->get()->groupBy('opd_service_detail_id'),
            $request,
        );

        return $this->standardPdfResponse($renderer, 'pdf.lab-report', 'OPD_Lab_Report_' . $opdId, [
            'patient'   => $opd->patient,
            'idLabel'   => 'OPD ID:',
            'idValue'   => $opdId,
            'dateLabel' => 'Visit Date:',
            'visitDate' => $opd->opd_date_time,
            'doctor'    => $opd->doctor?->staff_name,
            'services'  => $services,
        ]);
    }

    /** IPD Lab Investigation Report */
    public function ipdLabReport(int $ipdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);

        $services = $this->labServices(
            IpdService::query()->where('ipd_id', $ipdId),
            // BiochemistryTest model maps to biochemistry_tests_result (IPD lab results)
            BiochemistryTest::with('subTest.unit')->where('ipd_id', $ipdId)->get()->groupBy('ipd_service_detail_id'),
            $request,
        );

        return $this->standardPdfResponse($renderer, 'pdf.lab-report', 'IPD_Lab_Report_' . $ipdId, [
            'patient'   => $ipd->patient,
            'idLabel'   => 'IPD No:',
            'idValue'   => $ipd->admission_date_time
                ? ($ipd->id . '/bmh/' . Carbon::parse($ipd->admission_date_time)->format('Y'))
                : $ipdId,
            'dateLabel' => 'Admission Date:',
            'visitDate' => $ipd->admission_date_time,
            'doctor'    => $ipd->doctor?->staff_name,
            'services'  => $services,
        ]);
    }

    /** DCD Lab Investigation Report */
    public function dcdLabReport(int $dcdId, Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $dcd = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);

        $services = $this->labServices(
            DcdService::query()->where('dcd_id', $dcdId),
            DcdLabTestResult::with('subTest.unit')->where('dcd_id', $dcdId)->get()->groupBy('dcd_service_detail_id'),
            $request,
        );

        return $this->standardPdfResponse($renderer, 'pdf.lab-report', 'DCD_Lab_Report_' . $dcdId, [
            'patient'   => $dcd->patient,
            'idLabel'   => 'DCD ID:',
            'idValue'   => $dcdId,
            'dateLabel' => 'Visit Date:',
            'visitDate' => $dcd->dcd_date_time,
            'doctor'    => $dcd->doctor?->staff_name,
            'services'  => $services,
        ]);
    }

    /**
     * Shared OPD/IPD/DCD lab + radiology service list for pdf.lab-report.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query    service model query already scoped to the visit
     * @param  \Illuminate\Support\Collection         $results  lab results grouped by service-detail id
     */
    private function labServices($query, $results, Request $request): array
    {
        $serviceId = $request->query('service_id') ? (int) $request->query('service_id') : null;

        $rawServices = $query
            ->with(['details.charge', 'chargeDepartment', 'labNote.staff', 'radiologyNote.staff'])
            ->where(function ($q) {
                $q->whereHas('chargeDepartment', function ($cd) {
                    $cd->whereRaw('LOWER(category) IN (?, ?)', ['lab', 'radiology'])
                       ->orWhereRaw('LOWER(department_name) LIKE ?', ['%radiology%'])
                       ->orWhereRaw('LOWER(department_name) LIKE ?', ['%lab%'])
                       ->orWhereRaw('LOWER(department_name) LIKE ?', ['%biochemistry%'])
                       ->orWhereRaw('LOWER(department_name) LIKE ?', ['%pathology%']);
                });
            })
            ->when($serviceId, fn($q) => $q->where('id', $serviceId))
            ->orderBy('service_date_time')
            ->get();

        return $rawServices->map(function ($svc) use ($results) {
            $rawCategory = $svc->chargeDepartment?->category ?? '';
            $deptName    = $svc->chargeDepartment?->department_name ?? '';
            $isRadiology = (strtolower(trim($rawCategory)) === 'radiology') || (stripos($deptName, 'radiology') !== false);

            $chargeNames = $svc->details
                ->map(fn($d) => $d->charge?->charge_name)
                ->filter()
                ->unique()
                ->implode(', ');

            $noteObj = $isRadiology ? $svc->radiologyNote : $svc->labNote;

            $details = [];
            if (!$isRadiology) {
                $details = $svc->details->map(fn($d) => [
                    'charge_name' => $d->charge?->charge_name ?? '',
                    'results'     => ($results->get($d->id) ?? collect())
                        // Legacy inner-joined biochemistry_sub_tests: results without a sub-test are not listed.
                        ->filter(fn($r) => $r->subTest !== null)
                        ->map(fn($r) => [
                            'sub_test_name'   => $r->subTest->sub_test_name ?? '',
                            'test_value'      => $r->test_value,
                            'highlighted'     => in_array(strtolower(trim((string) $r->highlighted)), ['yes', '1', 'true'], true),
                            'reference_range' => $r->subTest->reference_range ?? '',
                            'unit'            => $r->subTest->unit?->unit_name ?? '',
                        ])->values()->all(),
                ])->all();
            }

            return [
                'id'           => $svc->id,
                'title'        => $svc->service_title,
                'date'         => $svc->service_date_time,
                'department'   => $deptName,
                'category'     => $rawCategory,
                'is_radiology' => $isRadiology,
                'charge_names' => $chargeNames,
                'note'         => $noteObj?->note,
                'note_title'   => $isRadiology ? 'Radiology Report:' : 'Conclusion / Interpretation:',
                'note_by'      => $noteObj?->staff?->staff_name,
                'note_date'    => $noteObj?->updated_on,
                'details'      => $details,
            ];
        })->all();
    }

    /**
     * Render a view that extends 'pdf.layouts.standard' (shared header + report-standard.css, docs/PDF_REPORTS.md)
     * and return it as an inline PDF. $settings and the signer (sign_name / sign_designation) are added here.
     *
     * @param  array<string,mixed>  $pdfConfig  extra mPDF options, e.g. ['format' => LegacyReportRenderer::HALF]
     */
    private function standardPdfResponse(LegacyReportRenderer $renderer, string $view, string $filename, array $data, array $pdfConfig = []): \Illuminate\Http\Response
    {
        $html = view($view, array_merge(
            ['settings' => $this->settings()],
            $data,
            $renderer->signer(),
        ))->render();

        return response($renderer->standard($html, $pdfConfig), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Admission Consent Form
    // ─────────────────────────────────────────────────────────

    /** IPD Admission Consent Form PDF (2-page: consent + blood transfusion) */
    public function admissionConsent(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $ipd  = Ipd::with(['patient', 'doctor', 'bed.wardCategory'])->findOrFail($ipdId);
        $file = IpdPatientFile::where('ipd_id', $ipdId)->first();

        return $this->standardPdfResponse($renderer, 'pdf.admission-consent', 'Admission_Consent_IPD_' . $ipdId, compact('ipd', 'file'));
    }

    // ─────────────────────────────────────────────────────────
    // General Receipt
    // ─────────────────────────────────────────────────────────

    /** Single charge receipt */
    public function receipt(int $chargeId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $charge = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])->findOrFail($chargeId);

        $patient = $this->chargePatient($charge);

        return $this->standardPdfResponse($renderer, 'pdf.receipt', 'Receipt_' . $chargeId, compact('charge', 'patient'));
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function settings(): array
    {
        return Cache::remember('hospital_settings', 600, fn() => Setting::find(1)?->toArray() ?? []);
    }

    // ───────────────────────────────────────────────────────
    // IPD Clinical Charts
    // ───────────────────────────────────────────────────────

    /** IPD Treatment Sheet PDF */
    public function ipdTreatmentPdf(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $d = $this->ipdData->treatmentChart($ipdId);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-treatment', 'IPD_Treatment_' . $ipdId, compact('d'));
    }

    /** IPD Vital Monitoring Chart PDF */
    public function ipdVitalMonitoringPdf(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $d = $this->ipdData->vitalMonitoring($ipdId);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-vital-monitoring', 'IPD_Vitals_' . $ipdId, compact('d'));
    }

    /** IPD Intake/Output Chart PDF */
    public function ipdIntakeOutputPdf(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $d = $this->ipdData->intakeOutput($ipdId);
        if (!$d) {
            abort(404, 'IPD admission not found');
        }

        return $this->standardPdfResponse($renderer, 'pdf.ipd-intake-output', 'IPD_Intake_Output_' . $ipdId, compact('d'));
    }

    // ───────────────────────────────────────────────────────
    // DCD Discharge Bill
    // ───────────────────────────────────────────────────────

    /** DCD Discharge Bill (mirrors IPD discharge bill) */
    public function dcdDischargeBill(int $dcdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $dcd     = Dcd::with(['patient', 'doctor', 'bed.wardCategory', 'discharge'])->findOrFail($dcdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'DCD')->where('module_id', $dcdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.dcd-discharge-bill', 'DCD_Discharge_Bill_' . $dcdId, compact('dcd', 'charges'));
    }

    // ───────────────────────────────────────────────────────
    // Sale Bills & Collections Print
    // ───────────────────────────────────────────────────────

    /** Sale Bills Print (date-range passed via query string) */
    public function saleBillsReport(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $from   = $request->input('from_date', now()->format('Y-m-d'));
        $to     = $request->input('to_date', now()->format('Y-m-d'));
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $from)) {
            $from = \DateTime::createFromFormat('d-m-Y', $from)->format('Y-m-d');
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $to)) {
            $to = \DateTime::createFromFormat('d-m-Y', $to)->format('Y-m-d');
        }
        $module = $request->input('module', '');
        $charges = BillingCharge::with(['chargeDepartment'])
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(charge_date_time)'), [$from, $to])
            ->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', '!=', 'Yes');
            })
            ->when($module, fn($q) => $q->where('module', $module))
            ->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.sale-bills', 'Sale_Bills_' . $from . '_' . $to, compact('charges', 'from', 'to', 'module'));
    }

    /** Collections Report Print */
    public function collectionsReport(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $from = $request->input('from_date', now()->format('Y-m-d'));
        $to   = $request->input('to_date', now()->format('Y-m-d'));
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $from)) {
            $from = \DateTime::createFromFormat('d-m-Y', $from)->format('Y-m-d');
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $to)) {
            $to = \DateTime::createFromFormat('d-m-Y', $to)->format('Y-m-d');
        }
        $charges = BillingCharge::with(['chargeDepartment'])
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(charge_date_time)'), [$from, $to])
            ->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', '!=', 'Yes');
            })
            ->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.collections', 'Collections_' . $from . '_' . $to, compact('charges', 'from', 'to'));
    }

    /** OPD Detailed Bill (itemised per charge) */
    public function opdDetailedBill(int $opdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $opd     = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->where('module', 'OPD')->where('module_id', $opdId)->orderBy('charge_date_time')->get();

        return $this->standardPdfResponse($renderer, 'pdf.opd-detailed-bill', 'OPD_Detailed_Bill_' . $opdId, compact('opd', 'charges'));
    }

    /** Selected invoices (multi) for OPD */
    public function opdSelectedBills(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        return $this->selectedBills($request, $renderer, 'OPD');
    }

    /** Selected invoices (multi) for IPD */
    public function ipdSelectedBills(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        return $this->selectedBills($request, $renderer, 'IPD');
    }

    /** Selected invoices (multi) for DCD */
    public function dcdSelectedBills(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        return $this->selectedBills($request, $renderer, 'DCD');
    }

    /** Shared OPD/IPD/DCD selected-invoices PDF (invoice_ids = comma-separated billing_charges ids). */
    private function selectedBills(Request $request, LegacyReportRenderer $renderer, string $module): \Illuminate\Http\Response
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->input('invoice_ids', ''))));
        if (empty($ids)) {
            return response('No invoices selected');
        }

        $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
            ->whereIn('id', $ids)->where('module', $module)->orderBy('charge_date_time')->get();
        $patient = $charges->isNotEmpty() ? $this->chargePatient($charges->first()) : null;

        return $this->standardPdfResponse($renderer, 'pdf.selected-bills', $module . '_Selected_Bills', compact('charges', 'patient'));
    }

    /** Patient of the OPD / IPD / DCD visit a billing charge belongs to. */
    private function chargePatient(BillingCharge $charge)
    {
        return match ($charge->module) {
            'OPD'   => Opd::with('patient')->find($charge->module_id)?->patient,
            'IPD'   => Ipd::with('patient')->find($charge->module_id)?->patient,
            'DCD'   => Dcd::with('patient')->find($charge->module_id)?->patient,
            default => null,
        };
    }

    /** IPD Patient File PDF (all clinical notes + patient file fields) */
    public function ipdPatientsFile(int $ipdId, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $ipd = Ipd::with(['patient', 'doctor', 'bed.wardCategory', 'patientFile', 'opd'])->findOrFail($ipdId);

        return $this->standardPdfResponse($renderer, 'pdf.ipd-patients-file', 'IPD_Patient_File_' . $ipdId, compact('ipd'));
    }

    /** Staff-wise Roster PDF */
    public function staffRosterPdf(Request $request, LegacyReportRenderer $renderer): \Illuminate\Http\Response
    {
        $yearInput  = $request->input('year');
        $monthInput = $request->input('month');

        if ($yearInput && $monthInput && is_numeric($monthInput)) {
            $start = \Carbon\Carbon::createFromDate((int) $yearInput, (int) $monthInput, 1)->startOfDay();
            $month = $start->format('Y-m');
        } elseif ($monthInput && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $start = \Carbon\Carbon::parse($monthInput . '-01')->startOfDay();
            $month = $monthInput;
        } else {
            $start = \Carbon\Carbon::now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = $start->copy()->endOfMonth();

        $designation = $request->input('designation');
        $shift       = $request->input('shift');

        $staffQuery = \App\Models\StaffMember::where('active', 'Yes');
        if (!empty($designation)) {
            $designationId = app(\App\Services\StaffService::class)->rosterDesignationId($designation);
            if ($designationId > 0) {
                $staffQuery->where('designation_id', $designationId);
            }
        }
        $staff = $staffQuery->orderBy('staff_name')->get(['id', 'staff_name']);

        $rosterQuery = \Illuminate\Support\Facades\DB::table('roster')
            ->whereBetween('roster_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        if (!empty($designation)) {
            $rosterQuery->where('designation', $designation);
        }
        if (!empty($shift)) {
            $rosterQuery->where('shift', $shift);
        }
        $roster = $rosterQuery->orderBy('roster_date')->get()->groupBy('staff_id');

        // Landscape: one column per day of the month.
        return $this->standardPdfResponse($renderer, 'pdf.staff-roster', 'Staff_Roster_' . $month, compact('staff', 'roster', 'month', 'start'), ['format' => 'A4-L']);
    }
}
