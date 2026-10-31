<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * DischargeSummary bundle of a day-care visit: admission data, discharge medications (from
 * dcd_discharge_treatment_advised only), procedures and discharge details. Lab results travel in the separate
 * DiagnosticReport bundle. Medications use structured FHIR timing.repeat like the IPD bundles.
 */
final class DischargeSummaryBundle
{
    private const SNOMED = 'http://snomed.info/sct';
    private const HL7_CODE_SYSTEM = 'http://terminology.hl7.org/CodeSystem/';

    public function __construct(
        private readonly CommonResources $common,
        private readonly DischargeSummaryPdfRenderer $pdf,
    ) {
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function build(int $dcdId): array
    {
        $visit = $this->loadVisit($dcdId);
        if (!$visit) {
            return ['success' => false, 'error' => 'DCD record not found'];
        }

        $ctx = BundleContext::open((string) $visit['dcd_date_time'], (string) $visit['doctor_name']);
        $summary = new SummarySections();

        $this->addCommonResources($ctx, $visit);
        $this->addPatientFileContent($ctx, $summary, $dcdId);
        $this->addMedications($ctx, $summary, $dcdId);
        $this->addProcedures($ctx, $summary, $dcdId);
        $this->addDischargeNote($visit, $summary);
        $this->addPdfDocumentReference($ctx, $dcdId);
        $this->addComposition($ctx, $summary, $dcdId);

        return ['success' => true, 'bundle' => DocumentBundle::forVisit($ctx->entries(), 'DCD-DischargeSummary-' . $dcdId)];
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $dcdId): ?array
    {
        return FhirQuery::first("SELECT dcds.*, patients.patient_name, patients.gender, patients.date_of_birth,
            patients.abha_number, patients.abha_address,
            staff_members.staff_name AS doctor_name,
            staff_members.hpr_id AS doctor_hpr_id,
            staff_members.medical_registration_number AS doctor_reg_number,
            staff_members.registration_council AS doctor_reg_council,
            dd.id AS discharge_id, dd.discharge_date_time, dd.diagnosis AS discharge_diagnosis,
            dd.condition_on_discharge, dd.follow_up_detail, dd.note AS discharge_note, dd.urgent_care_condition
            FROM dcds INNER JOIN patients ON patients.id = dcds.patient_id
            LEFT JOIN staff_members ON staff_members.id = dcds.consultant_doctor_id
            LEFT JOIN dcd_discharge dd ON dd.dcd_id = dcds.id WHERE dcds.id = ?", 'i', [$dcdId]);
    }

    /** @param array<string, mixed> $visit */
    private function addCommonResources(BundleContext $ctx, array $visit): void
    {
        $this->common->addPatient($ctx, $visit, true);
        $this->common->addOrganization($ctx);
        $this->common->addPractitioner($ctx, $visit);
        $this->common->addInpatientEncounter($ctx, !empty($visit['discharge_id']), $visit['discharge_date_time']);
    }

    /** Chief complaints, allergies, history and examination from the patient file (if one exists). */
    private function addPatientFileContent(BundleContext $ctx, SummarySections $summary, int $dcdId): void
    {
        $file = FhirQuery::first('SELECT * FROM dcd_patient_files WHERE dcd_id = ? LIMIT 1', 'i', [$dcdId]);
        if (!$file) {
            return;
        }

        $this->addChiefComplaint($ctx, $summary, $this->plain($file['chief_complaints'] ?? ''));
        $this->addAllergies($ctx, $summary, $this->plain($file['allergies'] ?? ''));

        $history = $this->plain($file['past_medical_history'] ?? '');
        if ($history !== '') {
            $summary->addText('Medical History', '1003642006', 'Past medical history section', $history);
        }
        $exam = trim(implode(' | ', array_filter([
            $this->plain($file['gcs'] ?? ''), $this->plain($file['pallor'] ?? ''), $this->plain($file['cns'] ?? ''),
            $this->plain($file['rs'] ?? ''), $this->plain($file['cvs'] ?? ''), $this->plain($file['per_abdomen'] ?? ''),
        ])));
        if ($exam !== '') {
            $summary->addText('Physical Examination', '425044008', 'Physical exam section', $exam);
        }
        // The provisional diagnosis only appears in the narrative, never as a section.
        $diagnosis = $this->plain($file['provisional_diagnosis'] ?? '');
        if ($diagnosis !== '') {
            $summary->addNarrative('<p><b>Provisional Diagnosis:</b> ' . $this->esc($diagnosis) . '</p>');
        }
    }

    private function addChiefComplaint(BundleContext $ctx, SummarySections $summary, string $chief): void
    {
        if ($chief === '') {
            return;
        }

        $id = abdm_generate_uuid();
        $ctx->add('Condition', $id, [
            'resourceType' => 'Condition',
            'id' => $id,
            'clinicalStatus' => ['coding' => [['system' => self::HL7_CODE_SYSTEM . 'condition-clinical', 'code' => 'active', 'display' => 'Active']]],
            'verificationStatus' => ['coding' => [['system' => self::HL7_CODE_SYSTEM . 'condition-ver-status', 'code' => 'confirmed', 'display' => 'Confirmed']]],
            'category' => [['coding' => [['system' => self::HL7_CODE_SYSTEM . 'condition-category', 'code' => 'encounter-diagnosis', 'display' => 'Encounter Diagnosis']]]],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'code' => ['text' => $chief],
            'recordedDate' => $ctx->now,
        ]);
        $summary->addEntries('Chief Complaints', '422843007', 'Chief complaint section', [['reference' => BundleContext::ref('Condition', $id)]]);
        $summary->addNarrative('<p><b>Chief Complaints:</b> ' . $this->esc($chief) . '</p>');
    }

    private function addAllergies(BundleContext $ctx, SummarySections $summary, string $allergies): void
    {
        if ($allergies === '') {
            return;
        }

        $id = abdm_generate_uuid();
        $ctx->add('AllergyIntolerance', $id, [
            'resourceType' => 'AllergyIntolerance',
            'id' => $id,
            'clinicalStatus' => ['coding' => [['system' => self::HL7_CODE_SYSTEM . 'allergyintolerance-clinical', 'code' => 'active', 'display' => 'Active']]],
            'verificationStatus' => ['coding' => [['system' => self::HL7_CODE_SYSTEM . 'allergyintolerance-verification', 'code' => 'confirmed', 'display' => 'Confirmed']]],
            'patient' => $ctx->patientReference(),
            'recordedDate' => $ctx->now,
            'code' => ['text' => $allergies],
        ]);
        $summary->addEntries('Allergies', '722446000', 'Allergy record', [['reference' => BundleContext::ref('AllergyIntolerance', $id)]]);
    }

    /** Discharge medications only (dcd_discharge_treatment_advised), one MedicationRequest each. */
    private function addMedications(BundleContext $ctx, SummarySections $summary, int $dcdId): void
    {
        $requester = $ctx->authorReference();
        $medEntries = [];
        foreach ($this->loadAdvisedMedicines($dcdId) as $advised) {
            $medicineName = trim($this->plain($advised['medicine_name'] ?? ''));
            if ($medicineName === '') {
                continue;
            }

            $medicationRequest = $this->medicationRequest($ctx, $advised, $medicineName, $requester);
            $ctx->add('MedicationRequest', $medicationRequest['id'], $medicationRequest);
            $medEntries[] = ['reference' => BundleContext::ref('MedicationRequest', $medicationRequest['id'])];
        }

        if ($medEntries) {
            $summary->addEntries('Medications', '1003606003', 'Medication history section', $medEntries);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function loadAdvisedMedicines(int $dcdId): array
    {
        return FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                ta.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                ta.duration_days,
                ta.medical_condition_id,
                mc.condition_name,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM dcd_discharge_treatment_advised ta
            INNER JOIN dcd_discharge ON dcd_discharge.id = ta.discharge_id
            INNER JOIN pharma_products pp ON ta.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON ta.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON ta.meal_relation_id = mr.id
            LEFT JOIN medical_conditions mc ON ta.medical_condition_id = mc.id
            LEFT JOIN dcd_discharge_treatment_timing_selections dts ON ta.id = dts.treatment_advised_id
            LEFT JOIN medicine_timing_of_day t ON dts.timing_id = t.id
            WHERE dcd_discharge.dcd_id = ?
            GROUP BY ta.id, pp.product_name, ta.dose_quantity, ft.display_text, mr.relation_name, ta.duration_days, ta.medical_condition_id, mc.condition_name
            ORDER BY ta.id", 'i', [$dcdId]);
    }

    /**
     * @param array<string, mixed> $advised
     * @return array<string, mixed>
     */
    private function medicationRequest(BundleContext $ctx, array $advised, string $medicineName, string $requester): array
    {
        $dosage = MedicationDosage::fromRow($advised);

        $request = [
            'resourceType' => 'MedicationRequest',
            'id' => abdm_generate_uuid(),
            'status' => 'active',
            'intent' => 'order',
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'authoredOn' => $ctx->now,
            'requester' => ['reference' => $requester],
            'medicationCodeableConcept' => ['text' => $medicineName],
        ];
        if (!empty($advised['condition_name'])) {
            $request['reasonCode'] = [['text' => trim($this->plain($advised['condition_name']))]];
        }
        $request['dosageInstruction'] = [$this->dosageInstruction($dosage)];

        $dispenseRequest = $this->dispenseRequest($advised, $dosage->frequency);
        if ($dispenseRequest !== null) {
            $request['dispenseRequest'] = $dispenseRequest;
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function dosageInstruction(MedicationDosage $dosage): array
    {
        return [
            'text' => $dosage->text,
            'timing' => [
                'repeat' => [
                    'frequency' => $dosage->frequency,
                    'period' => 1,
                    'periodUnit' => 'd',
                    'when' => $dosage->whenCodes,
                ],
            ],
            'route' => ['coding' => [['system' => self::SNOMED, 'code' => '26643006', 'display' => 'Oral route']]],
            // Legacy quirk: the dose quantity is the number of daily doses, not the tablets per dose.
            'doseAndRate' => [['doseQuantity' => ['value' => $dosage->frequency, 'unit' => 'tablets']]],
        ];
    }

    /**
     * Total quantity to dispense, only when both dose and duration are numeric.
     *
     * @param array<string, mixed> $advised
     * @return array<string, mixed>|null
     */
    private function dispenseRequest(array $advised, int $frequency): ?array
    {
        if (empty($advised['dose_quantity']) || empty($advised['duration_days'])
            || !is_numeric($advised['dose_quantity']) || !is_numeric($advised['duration_days'])) {
            return null;
        }

        $totalQuantity = (float) $advised['dose_quantity'] * $frequency * (int) $advised['duration_days'];

        return [
            'quantity' => ['value' => $totalQuantity, 'unit' => 'tablets'],
            'expectedSupplyDuration' => [
                'value' => (int) $advised['duration_days'],
                'unit' => 'days',
                'system' => 'http://unitsofmeasure.org',
                'code' => 'd',
            ],
        ];
    }

    private function addProcedures(BundleContext $ctx, SummarySections $summary, int $dcdId): void
    {
        $procedures = FhirQuery::rows("SELECT s.id, s.service_title, s.service_date_time, s.status FROM dcd_services s INNER JOIN charge_departments c ON c.id = s.charge_department_id WHERE s.dcd_id = ? AND c.category = 'Procedure'", 'i', [$dcdId]);

        $procEntries = [];
        foreach ($procedures as $procedure) {
            $id = abdm_generate_uuid();
            $procEntries[] = ['reference' => BundleContext::ref('Procedure', $id)];
            $ctx->add('Procedure', $id, [
                'resourceType' => 'Procedure',
                'id' => $id,
                'status' => 'completed',
                'subject' => $ctx->patientReference(),
                'encounter' => $ctx->encounterReference(),
                'code' => ['text' => $procedure['service_title']],
                'performedDateTime' => BundleContext::timestamp((string) $procedure['service_date_time']),
            ]);
        }

        if ($procEntries) {
            $summary->addEntries('Procedures', '1003640003', 'History of past procedure section', $procEntries);
        }
    }

    /** @param array<string, mixed> $visit */
    private function addDischargeNote(array $visit, SummarySections $summary): void
    {
        $fields = [
            ['Diagnosis', $visit['discharge_diagnosis'] ?? ''],
            ['Condition on Discharge', $visit['condition_on_discharge'] ?? ''],
            ['Follow-up', $visit['follow_up_detail'] ?? ''],
            ['Note', $visit['discharge_note'] ?? ''],
            ['Urgent Care', $visit['urgent_care_condition'] ?? ''],
        ];
        $parts = [];
        foreach ($fields as [$label, $value]) {
            $value = $this->plain($value);
            if ($value !== '') {
                $parts[] = '<p><b>' . $label . ':</b> ' . $this->esc($value) . '</p>';
            }
        }

        if (!$parts) {
            return;
        }

        $div = '<div xmlns="http://www.w3.org/1999/xhtml">' . implode('', $parts) . '</div>';

        $summary->add([
            'title' => 'Discharge Note',
            'code' => ['coding' => [['system' => self::SNOMED, 'code' => '373942005', 'display' => 'Discharge summary']]],
            'text' => ['status' => 'generated', 'div' => $div],
        ]);
        foreach ($parts as $part) {
            $summary->addNarrative($part);
        }
    }

    /**
     * Attaches the rendered discharge summary PDF as a DocumentReference (skipped when no PDF could be produced).
     *
     * @return string|null DocumentReference id or null
     */
    private function addPdfDocumentReference(BundleContext $ctx, int $dcdId): ?string
    {
        $dischargePdf = $this->pdf->render($dcdId);
        if ($dischargePdf === null) {
            return null;
        }

        $docRefId = abdm_generate_uuid();
        $ctx->add('DocumentReference', $docRefId, [
            'resourceType' => 'DocumentReference',
            'id' => $docRefId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentReference']],
            'status' => 'current',
            'docStatus' => 'final',
            'type' => [
                'coding' => [['system' => self::SNOMED, 'code' => '373942005', 'display' => 'Discharge Summary']],
                'text' => 'Discharge Summary',
            ],
            'subject' => $ctx->patientReference(),
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language' => 'en-IN',
                    'data' => base64_encode($dischargePdf),
                    'title' => 'DCD Discharge Summary',
                    'creation' => $ctx->now,
                ],
            ]],
        ]);

        return $docRefId;
    }

    private function addComposition(BundleContext $ctx, SummarySections $summary, int $dcdId): void
    {
        $compositionId = abdm_generate_uuid();
        $ctx->prepend('Composition', $compositionId, [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DischargeSummaryRecord']],
            'identifier' => ['system' => 'https://ndhm.in/phr', 'value' => 'DCD-DischargeSummary-' . $dcdId],
            'status' => 'final',
            'type' => [
                'coding' => [['system' => self::SNOMED, 'code' => '373942005', 'display' => 'Discharge summary']],
                'text' => 'DCD Discharge Summary',
            ],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorReference()]],
            'title' => 'DCD Discharge Summary',
            'custodian' => $ctx->organizationReference(),
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . $summary->narrativeHtml() . '</div>'],
            'section' => $summary->sections(),
        ]);
    }

    private function plain(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
