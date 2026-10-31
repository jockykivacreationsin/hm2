<?php

namespace App\Services\Abdm\Fhir;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

/**
 * Builds a FHIR R4 HealthDocumentRecord bundle for ABDM HI type "HealthDocumentRecord".
 *
 * Source table: patient_documents (+ document_types for SNOMED metadata).
 * One bundle per visit (OPD/IPD/DCD). Each active document becomes a FHIR
 * DocumentReference resource. The file content is base64-encoded into the bundle.
 */
class HealthDocumentFhirBuilder
{
    use FhirBundleTrait;

    private const DISK    = 'documents';
    private const MAX_B64 = 20 * 1024 * 1024; // 20 MB per document

    protected function kind(): string
    {
        return 'HEALTH_DOCUMENT';
    }

    /**
     * @param string      $module   'OPD' | 'IPD' | 'DCD'
     * @param int         $id       visit primary key
     * @param string|null $dateFrom consent range start (ISO-8601 UTC)
     * @param string|null $dateTo   consent range end   (ISO-8601 UTC)
     * @return array|null FHIR Bundle or null when nothing to share
     */
    public function buildBundle(string $module, int $id, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $module = strtoupper($module);
        if (!in_array($module, ['OPD', 'IPD', 'DCD'], true)) {
            return null;
        }

        $visitTable = strtolower($module) . 's';
        $visit      = DB::table($visitTable)->where('id', $id)->first();
        if (!$visit) {
            return null;
        }

        $patient = DB::table('patients')->where('id', $visit->patient_id ?? null)->first();
        if (!$patient) {
            return null;
        }

        $bounds    = $this->bounds($dateFrom, $dateTo);
        $moduleCol = strtolower($module) . '_id';

        $docs = DB::table('patient_documents')
            ->leftJoin('document_types', 'document_types.id', '=', 'patient_documents.document_type_id')
            ->where('patient_documents.' . $moduleCol, $id)
            ->where('patient_documents.status', '!=', 'entered-in-error')
            ->select(
                'patient_documents.*',
                'document_types.type_name',
                'document_types.snomed_code',
                'document_types.snomed_display'
            )
            ->orderBy('patient_documents.document_date')
            ->get();

        if ($docs->isEmpty()) {
            return null;
        }

        $patientResource      = $this->buildPatientResource($patient);
        $organizationResource = $this->buildOrganizationResource();
        $encounterId          = (string) Str::uuid();

        $doctorId        = $visit->consultant_doctor_id ?? null;
        $doctorRow       = $doctorId ? DB::table('staff_members')->where('id', $doctorId)->first() : null;
        $practitionerRes = $this->buildPractitionerResource($doctorRow);

        $context = [
            'patient'      => $patientResource['id'],
            'organization' => $organizationResource['id'],
            'encounter'    => $encounterId,
            'practitioner' => $practitionerRes ? $practitionerRes['id'] : null,
        ];

        $date    = match ($module) { 'OPD' => $visit->opd_date_time, 'IPD' => $visit->admission_date_time, default => $visit->dcd_date_time };
        $entries = [$patientResource, $organizationResource];
        if ($practitionerRes) {
            $entries[] = $practitionerRes;
        }
        $entries[] = $this->buildSimpleEncounter($visit, $module, $context, $date);

        $docRefResources = [];
        foreach ($docs as $doc) {
            if (!$this->inRange($doc->document_date, $bounds)) {
                continue;
            }
            $res = $this->buildDocumentReference($doc, $context);
            if ($res) {
                $docRefResources[] = $res;
            }
        }

        if (!$docRefResources) {
            return null;
        }

        $sections = [[
            'title' => 'Health Documents',
            'code'  => $this->concept('http://snomed.info/sct', '371530004', 'Clinical consultation report'),
            'entry' => array_map(fn ($r) => $this->reference($r['id']), $docRefResources),
        ]];

        $composition = $this->buildCompositionResource($context, $sections, ['HealthDocumentRecord'], $date, $module);
        $composition['identifier'] = ['system' => 'https://ndhm.in/phr', 'value' => "{$module}-HealthDocumentRecord-{$id}"];
        array_push($entries, ...$docRefResources);
        array_unshift($entries, $composition);

        return $this->buildDocumentBundle($entries, "{$module}-HealthDocumentRecord-{$id}");
    }

    private function buildSimpleEncounter(object $visit, string $module, array $context, $date): array
    {
        $resource = [
            'resourceType'   => 'Encounter',
            'id'             => $context['encounter'],
            'status'         => 'finished',
            'class'          => [
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code'    => $module === 'OPD' ? 'AMB' : 'IMP',
                'display' => $module === 'OPD' ? 'ambulatory' : 'inpatient encounter',
            ],
            'subject'        => $this->reference($context['patient']),
            'serviceProvider'=> $this->reference($context['organization']),
        ];
        if (!empty($context['practitioner'])) {
            $resource['participant'] = [['individual' => $this->reference($context['practitioner'])]];
        }
        if ($date) {
            $resource['period'] = ['start' => $this->iso($date), 'end' => $this->iso($date)];
        }
        return $resource;
    }

    private function buildDocumentReference(object $doc, array $context): ?array
    {
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($doc->file_path)) {
            return null;
        }

        $fileSize = $disk->size($doc->file_path);
        if ($fileSize > self::MAX_B64) {
            return null; // skip extremely large files to avoid OOM
        }

        $b64 = base64_encode($disk->get($doc->file_path));

        $resource = [
            'resourceType'  => 'DocumentReference',
            'id'            => (string) Str::uuid(),
            'meta'          => $this->profile('DocumentReference'),
            'status'        => 'current',
            'subject'       => $this->reference($context['patient']),
            'context'       => ['encounter' => [$this->reference($context['encounter'])]],
        ];

        if ($doc->snomed_code) {
            $resource['type'] = $this->concept('http://snomed.info/sct', $doc->snomed_code, $doc->snomed_display ?? $doc->type_name ?? '');
        } elseif ($doc->type_name) {
            $resource['type'] = ['text' => $doc->type_name];
        }

        if ($doc->document_date) {
            $resource['date'] = $this->iso($doc->document_date) ?? CarbonImmutable::now()->toIso8601String();
        }

        $resource['description'] = $doc->title ?? $doc->original_file_name ?? 'Document';

        $resource['content'] = [[
            'attachment' => [
                'contentType' => $doc->mime_type ?? 'application/octet-stream',
                'data'        => $b64,
                'title'       => $doc->original_file_name ?? 'document',
                'hash'        => $doc->sha256 ? base64_encode(hex2bin($doc->sha256)) : null,
                'size'        => $fileSize,
            ],
        ]];

        return $resource;
    }
}
