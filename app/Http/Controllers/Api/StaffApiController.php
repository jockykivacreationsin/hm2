<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints of the QR-scanner attendance kiosk — ports of the legacy bmhabdm/api/attendance.php and
 * bmhabdm/api/staff_members.php. Response shapes are unchanged. The kiosk has no user session, so the routes
 * are protected by the shared-key `kiosk.key` middleware (KIOSK_API_KEY / X-Kiosk-Key).
 */
class StaffApiController extends Controller
{
    public function __construct(private StaffService $staff)
    {
    }

    /** POST api/staff/attendance  {"staff_id": 12}  → check-in / check-out for today. */
    public function attendance(Request $request): JsonResponse
    {
        // Legacy read the raw JSON body; also accept a normal form post.
        $staffId = $request->json('staff_id', $request->input('staff_id'));

        if (!is_scalar($staffId) || !ctype_digit((string) $staffId) || (int) $staffId <= 0) {
            return response()->json(['success' => false, 'message' => 'Staff ID is required']);
        }

        return response()->json($this->staff->qrScan((int) $staffId));
    }

    /** GET api/staff-members → [{id, staff_name, photo(url)}] for active staff with a photo. */
    public function members(): JsonResponse
    {
        return response()->json($this->staff->photoList(), 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
