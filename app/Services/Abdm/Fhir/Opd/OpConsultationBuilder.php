<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * hiType OPConsultation: vitals, chief complaint, procedures, notes and the visit-summary PDF.
 * Medications and lab results are separate HI types.
 */
final class OpConsultationBuilder
{
    private const TITLE = 'OP Consultation';

    private const SNOMED_CODE = '371530004';

    private const SNOMED_DISPLAY = 'Clinical consultation report';

    /** visit column => [LOINC code, LOINC display, unit, UCUM code]; blood pressure is handled separately. */
    private const VITALS = [
        'pulse' => ['8867-4', 'Heart rate', 'beats/minute', '/min'],
        'temperature' => ['8310-5', 'Body temperature', 'degF', '[degF]'],
        'respiration' => ['9279-1', 'Respiratory rate', 'breaths/minute', '/min'],
        'spo2' => ['2708-6', 'Oxygen saturation', '%', '%'],
        'height' => ['8302-2', 'Body height', 'cm', 'cm'],
        'weight' => ['29463-7', 'Body weight', 'kg', 'kg'],
    ];

    public function __construct(
        private readonly OpdBundleAssembler $assembler,
        private readonly FhirObservationFactory $observations,
        private readonly OpdVisitSummaryPdf $visitSummary,
    ) {
    }

    /** @return array{success: bool, bundle: array<string, mixed>} */
    public function build(OpdBundleContext $context): array
    {
        if (!$this->hasClinicalData($context->visit)) {
            error_log("[ABDM][OPD][OPCONSULTATION] No clinical data found for OPD #{$context->opdId} - returning placeholder bundle");

            return $this->assembler->placeholder($context, 'OPConsultation', self::TITLE, self::SNOMED_CODE, self::SNOMED_DISPLAY);
        }

        $context->startDocument();

        $vitalRefs = $this->addVitals($context);
        $procedureRefs = $this->addProcedures($context);
        $documentRefs = $this->addVisitSummary($context);

        $chiefComplaint = $this->plainText($context->visit, 'symptoms');
        $remarks = $this->plainText($context->visit, 'remarks');

        $composition = $this->assembler->composition(
            $context,
            self::TITLE,
            $this->assembler->snomed(self::SNOMED_CODE, self::SNOMED_DISPLAY)['coding'],
            $this->narrative($chiefComplaint, $remarks),
            $this->sections($chiefComplaint, $remarks, $vitalRefs, $procedureRefs, $documentRefs),
        );

        return $this->assembler->finish($context, $composition);
    }

    /**
     * Legacy rule: empty(trim(...)) is used, so a literal "0" does not count as data.
     */
    private function hasClinicalData(array $visit): bool
    {
        foreach (['symptoms', 'remarks'] as $field) {
            if (!empty($this->plainText($visit, $field))) {
                return true;
            }
        }
        foreach (['height', 'weight', 'bp', 'pulse', 'temperature', 'respiration', 'spo2'] as $field) {
            if (!empty(trim((string) ($visit[$field] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    /** Free-text visit column with HTML (Summernote) stripped. */
    private function plainText(array $visit, string $field): string
    {
        return trim(strip_tags((string) ($visit[$field] ?? '')));
    }

    /** @return array<int, array{reference: string}> */
    private function addVitals(OpdBundleContext $context): array
    {
        $refs = [];
        foreach (self::VITALS as $field => $definition) {
            $raw = trim((string) ($context->visit[$field] ?? ''));
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $obsId = abdm_generate_uuid();
            $refs[] = $context->add('Observation', $obsId, $this->observations->vital($context, $obsId, $definition, (float) $raw));
        }

        $bpText = trim((string) ($context->visit['bp'] ?? ''));
        if ($bpText !== '') {
            // Legacy quirk: the id is drawn even when the text is not parseable as "120/80" (keeps ids stable).
            $bpId = abdm_generate_uuid();
            $bpResource = $this->observations->bloodPressure($context, $bpId, $bpText);
            if ($bpResource) {
                $refs[] = $context->add('Observation', $bpId, $bpResource);
            }
        }

        return $refs;
    }

    /** @return array<int, array{reference: string}> */
    private function addProcedures(OpdBundleContext $context): array
    {
        $procedures = FhirQuery::rows("SELECT opd_services.id, opd_services.service_title, opd_services.service_date_time, opd_services.status
            FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id
            WHERE opd_services.opd_id = ? AND charge_departments.category = 'Procedure'", 'i', [$context->opdId]);

        $refs = [];
        foreach ($procedures as $procedure) {
            $procId = abdm_generate_uuid();
            $refs[] = $context->add('Procedure', $procId, $this->procedure($context, $procedure, $procId));
        }

        return $refs;
    }

    /** @return array<string, mixed> */
    private function procedure(OpdBundleContext $context, array $procedure, string $procId): array
    {
        $items = FhirQuery::rows("SELECT charges.charge_name FROM opd_service_detail
                INNER JOIN charges ON charges.id = opd_service_detail.charge_id
                WHERE opd_service_detail.opd_service_id = ?", 'i', [$procedure['id']]);
        $itemNames = array_column($items, 'charge_name');

        $resource = [
            'resourceType' => 'Procedure',
            'id' => $procId,
            // OPD visits are historical records shared via ABDM, so procedures are always completed.
            'status' => 'completed',
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'code' => ['text' => $procedure['service_title'] . (!empty($itemNames) ? ' (' . implode(', ', $itemNames) . ')' : '')],
            'performedDateTime' => OpdBundleContext::dateTime($procedure['service_date_time']),
        ];

        $note = FhirQuery::first("SELECT note FROM opd_procedure_notes WHERE opd_service_id = ?", 'i', [$procedure['id']]);
        $cleanNote = trim(strip_tags((string) ($note['note'] ?? '')));
        if (!empty($note['note']) && $cleanNote !== '') {
            $resource['note'] = [['text' => $cleanNote]];
        }

        return $resource;
    }

    /** @return array<int, array{reference: string}> */
    private function addVisitSummary(OpdBundleContext $context): array
    {
        $pdf = $this->visitSummary->render($context->opdId);
        if ($pdf === null) {
            return [];
        }

        $docRefId = abdm_generate_uuid();

        return [$context->add('DocumentReference', $docRefId, [
            'resourceType' => 'DocumentReference',
            'id' => $docRefId,
            'status' => 'current',
            'type' => $this->assembler->snomed(self::SNOMED_CODE, self::SNOMED_DISPLAY),
            'subject' => $context->subject(),
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'data' => base64_encode($pdf),
                    'title' => 'OPD Visit Summary',
                ],
            ]],
        ])];
    }

    private function narrative(string $chiefComplaint, string $remarks): string
    {
        $parts = [];
        if ($chiefComplaint !== '') {
            $parts[] = '<p><b>Chief Complaint:</b> ' . Xhtml::escape($chiefComplaint) . '</p>';
        }
        if ($remarks !== '') {
            $parts[] = '<p><b>Notes:</b> ' . Xhtml::escape($remarks) . '</p>';
        }
        if (empty($parts)) {
            $parts[] = '<p>OP Consultation record.</p>';
        }

        return Xhtml::div(implode('', $parts));
    }

    /**
     * @param array<int, array{reference: string}> $vitalRefs
     * @param array<int, array{reference: string}> $procedureRefs
     * @param array<int, array{reference: string}> $documentRefs
     * @return array<int, array<string, mixed>>
     */
    private function sections(string $chiefComplaint, string $remarks, array $vitalRefs, array $procedureRefs, array $documentRefs): array
    {
        $sections = [];
        if ($chiefComplaint !== '') {
            $sections[] = [
                'title' => 'Chief Complaint',
                'code' => $this->assembler->snomed('422843007', 'Chief complaint section'),
                'text' => ['status' => 'generated', 'div' => Xhtml::div(Xhtml::escape($chiefComplaint))],
            ];
        }
        if (!empty($vitalRefs)) {
            $sections[] = $this->assembler->entrySection('Vital Signs', $this->assembler->snomed('425044008', 'Physical exam section'), $vitalRefs);
        }
        if (!empty($procedureRefs)) {
            $sections[] = $this->assembler->entrySection('Procedures', $this->assembler->snomed('371525003', 'Clinical procedure report'), $procedureRefs);
        }
        if ($remarks !== '') {
            $sections[] = [
                'title' => 'Notes',
                'text' => ['status' => 'generated', 'div' => Xhtml::div(Xhtml::escape($remarks))],
            ];
        }
        if (!empty($documentRefs)) {
            $sections[] = $this->assembler->entrySection('Clinical Document', $this->assembler->snomed(self::SNOMED_CODE, self::SNOMED_DISPLAY), $documentRefs);
        }

        return $sections;
    }
}
