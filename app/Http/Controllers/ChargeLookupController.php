<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\ChargeDepartment;
use App\Services\ChargeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared GET lookups for the charge pickers and the OPD / IPD / DCD charge + prescription modals
 * (routes `charges.lookup.*`, declared in routes/parity/charges.php).
 */
class ChargeLookupController extends Controller
{
    public function __construct(private ChargeLookupService $lookup) {}

    /** select2 source: active charge departments (`q`). */
    public function departments(Request $request): JsonResponse
    {
        return response()->json($this->lookup->departments((string) $request->input('q', '')));
    }

    /** select2 source: charge categories (`q`, optional `charge_department_id`). */
    public function categories(Request $request): JsonResponse
    {
        return response()->json($this->lookup->categories(
            (int) $request->input('charge_department_id', 0),
            (string) $request->input('q', '')
        ));
    }

    /** select2 source: charges (`q`, optional `charge_department_id`). */
    public function charges(Request $request): JsonResponse
    {
        return response()->json($this->lookup->charges(
            (int) $request->input('charge_department_id', 0),
            (string) $request->input('q', '')
        ));
    }

    /** Amount a patient category pays for a charge: `id`, `patient_category_id` => {amount}. */
    public function amount(Request $request): JsonResponse
    {
        return response()->json([
            'amount' => $this->lookup->amount((int) $request->input('id'), (int) $request->input('patient_category_id')),
        ]);
    }

    /** `<option>` list of the test groups in a prescription: `id`, `department` (OPD|IPD|DCD) => {html}. */
    public function prescriptionDetails(Request $request): JsonResponse
    {
        $department = (string) $request->input('department', 'OPD');

        return response()->json(['html' => $this->lookup->prescriptionOptionsHtml((int) $request->input('id'), $department)]);
    }

    /**
     * Table of the charges of a prescription test group: `type`, `values` (comma separated charge ids) => {html}.
     * With `department` (OPD|IPD|DCD) + `prescription_id` the items already billed on that visit are left out, and so
     * are those in `exclude[]` (queued in the page's table, not saved yet).
     */
    public function prescriptionCharges(Request $request): JsonResponse
    {
        return response()->json([
            'html' => $this->lookup->prescriptionChargesHtml(
                (string) $request->input('type', ''),
                (string) $request->input('values', ''),
                (string) $request->input('department', ''),
                (int) $request->input('prescription_id', 0),
                (array) $request->input('exclude', [])
            ),
        ]);
    }

    /** Department / category / amount to pre-fill for a prescription charge: `charge_id`, `patient_category_id`. */
    public function chargeForPrescription(Request $request): JsonResponse
    {
        $data = $this->lookup->chargeForPrescription((int) $request->input('charge_id'), (int) $request->input('patient_category_id'));

        return response()->json($data ?? ['success' => false, 'message' => 'Charge not found']);
    }

    public function departmentName(Request $request): JsonResponse
    {
        $dept = ChargeDepartment::find((int) ($request->input('department_id') ?: $request->input('charge_department_id')));

        return response()->json([
            'success'         => (bool) $dept,
            'department_name' => $dept?->department_name ?? '',
        ]);
    }

    public function categoryName(Request $request): JsonResponse
    {
        $cat = ChargeCategory::find((int) ($request->input('category_id') ?: $request->input('charge_category_id')));

        return response()->json([
            'success'         => (bool) $cat,
            'category_name'   => $cat?->charge_category ?? '',
            'charge_category' => $cat?->charge_category ?? '',
        ]);
    }

    public function chargeName(Request $request): JsonResponse
    {
        $charge = Charge::find((int) ($request->input('charge_id') ?: $request->input('id')));

        return response()->json([
            'success'     => (bool) $charge,
            'charge_name' => $charge?->charge_name ?? '',
        ]);
    }
}
