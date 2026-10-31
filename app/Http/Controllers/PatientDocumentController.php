<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\PatientDocument;
use App\Services\PermissionService;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Patient document upload, listing, secure download, and soft-delete.
 *
 * Files are stored on the private `documents` disk (outside webroot). The `file_path`
 * column holds a bare Storage key relative to that disk root. Downloads are always
 * gated by permission — the file is never served from a public URL.
 */
class PatientDocumentController extends Controller
{
    private const DISK         = 'documents';
    private const MAX_MB       = 20;
    private const ALLOWED_MIME = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Render the Patient Documents page for a given visit module (opd|ipd|dcd).
     * Route param is {id}; $module is injected via route default.
     */
    public function page(int $id, PermissionService $ps, string $module = 'opd'): View
    {
        $permission = match ($module) {
            'opd' => 'im_patient_documents',
            'ipd' => 'im_patient_documents',
            'dcd' => 'im_patient_documents',
            default => null,
        };
        abort_if($permission === null, 404);
        abort_unless($ps->hasPermission($permission), 403);

        $table = match ($module) { 'opd' => 'opds', 'ipd' => 'ipds', 'dcd' => 'dcds', default => null };
        abort_if($table === null, 404);
        $visit = DB::table($table)->where('id', $id)->first();
        abort_if($visit === null, 404);

        $patientId = (int) $visit->patient_id;

        return view('patient-documents.page', compact('module', 'visit', 'patientId', 'id'));
    }

    /** List documents for a patient (JSON). */
    public function index(Request $request, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('im_patient_documents'), 403);

        $patientId = (int) $request->input('patient_id');
        if ($patientId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid patient_id.']);
        }

        $docs = PatientDocument::with('documentType')
            ->where('patient_id', $patientId)
            ->where('status', '!=', 'entered-in-error')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($d) => [
                'id'             => $d->id,
                'title'          => $d->title,
                'document_date'  => $d->document_date,
                'type_name'      => $d->documentType?->type_name ?? '',
                'original_file_name' => $d->original_file_name,
                'mime_type'      => $d->mime_type,
                'file_size'      => $d->file_size,
                'remarks'        => $d->remarks,
            ]);

        return response()->json(['success' => true, 'data' => $docs]);
    }

    /** Upload a new document. */
    public function store(Request $request, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('b_document_upload'), 403);

        $patientId = (int) $request->input('patient_id');
        if ($patientId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid patient_id.']);
        }

        if (!$request->hasFile('document_file') || !$request->file('document_file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No valid file uploaded.']);
        }

        $file = $request->file('document_file');

        if ($file->getSize() > self::MAX_MB * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => 'File exceeds ' . self::MAX_MB . ' MB limit.']);
        }

        $mime = $file->getMimeType() ?? '';
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return response()->json(['success' => false, 'message' => 'File type not allowed.']);
        }

        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $storedName   = 'doc_' . $patientId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path         = 'patients/' . $patientId . '/' . $storedName;

        $sha256 = hash_file('sha256', $file->getRealPath());
        Storage::disk(self::DISK)->putFileAs('patients/' . $patientId, $file, $storedName);

        $titleInput = trim((string) $request->input('title', ''));
        $dateInput  = $request->input('document_date');
        $docDate    = $dateInput ? date('Y-m-d', strtotime($dateInput)) : now()->toDateString();

        $doc = PatientDocument::create([
            'patient_id'        => $patientId,
            'document_type_id'  => (int) $request->input('document_type_id') ?: null,
            'title'             => $titleInput !== '' ? $titleInput : $originalName,
            'document_date'     => $docDate,
            'file_path'         => $path,
            'original_file_name'=> $originalName,
            'mime_type'         => $mime,
            'file_size'         => $file->getSize(),
            'sha256'            => $sha256,
            'opd_id'            => (int) $request->input('opd_id') ?: null,
            'ipd_id'            => (int) $request->input('ipd_id') ?: null,
            'dcd_id'            => (int) $request->input('dcd_id') ?: null,
            'status'            => 'active',
            'remarks'           => $request->input('remarks'),
            'created_on'        => now()->toDateTimeString(),
            'user_id'           => CurrentUser::id(),
        ]);

        return response()->json(['success' => true, 'id' => $doc->id, 'message' => 'Document uploaded successfully.']);
    }

    /** Secure download — checks permission before streaming the file. */
    public function download(int $id, PermissionService $ps): StreamedResponse
    {
        abort_unless($ps->hasPermission('im_patient_documents'), 403);

        $doc = PatientDocument::findOrFail($id);
        abort_if($doc->status === 'entered-in-error', 404);

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($doc->file_path), 404);

        return $disk->response(
            $doc->file_path,
            $doc->original_file_name ?? basename($doc->file_path),
            [
                'Content-Type'           => $doc->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'private, no-store',
            ],
            'attachment'
        );
    }

    /** Soft-delete — sets status to 'entered-in-error', does NOT remove the file. */
    public function destroy(int $id, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('b_document_delete'), 403);

        $doc = PatientDocument::findOrFail($id);
        $doc->status = 'entered-in-error';
        $doc->save();

        return response()->json(['success' => true, 'message' => 'Document removed.']);
    }

    /** Picker: document types for the upload modal. */
    public function types(): JsonResponse
    {
        $types = DocumentType::where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('type_name')
            ->get(['id', 'type_name', 'type_code']);

        return response()->json(['success' => true, 'data' => $types]);
    }
}
