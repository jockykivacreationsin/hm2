<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * Prescription bundle of a day-care visit (NRCeS PrescriptionRecord profile, closed slice).
 * Carries ONLY the admission prescriptions from dcd_medicines; discharge medications belong to the DischargeSummary.
 * The prescription PDF is attached as a Binary resource.
 */
final class PrescriptionBundle
{
    private const LOG = '[ABDM][DCD][PRESCRIPTION]';

    public function __construct(
        private readonly CommonResources $common,
        private readonly PrescriptionPdfRenderer $pdf,
    ) {
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function build(int $dcdId): array
    {
        error_log(self::LOG . " Starting build for DCD ID=$dcdId");

        $visit = $this->loadVisit($dcdId);
        if (!$visit) {
            error_log(self::LOG . " ERROR: DCD record not found for ID=$dcdId");
            return ['success' => false, 'error' => 'DCD record not found'];
        }
        error_log(self::LOG . " Found DCD ID=$dcdId");

        $ctx = BundleContext::open((string) $visit['dcd_date_time'], (string) $visit['doctor_name']);
        error_log(self::LOG . " Generated UUIDs - Patient={$ctx->patientId}, Org={$ctx->orgId}, Encounter={$ctx->encounterId}, Practitioner={$ctx->practitionerId}");

        $this->addCommonResources($ctx, $visit);

        $sectionEntries = $this->addAdmissionMedications($ctx, $dcdId);
        error_log(self::LOG . " Total admission medications: " . count($sectionEntries));
        if (empty($sectionEntries)) {
            error_log(self::LOG . " ERROR: No medications found");
            return ['success' => false, 'error' => 'No medications recorded for this DCD visit'];
        }
        error_log(self::LOG . " Total medications: " . count($sectionEntries));

        $binaryEntry = $this->addPdfBinary($ctx, $dcdId);
        if ($binaryEntry !== null) {
            $sectionEntries[] = $binaryEntry;
        }

        $this->addComposition($ctx, $sectionEntries, $dcdId);
        $bundle = DocumentBundle::forVisit($ctx->entries(), 'DCD-Prescription-' . $dcdId);

        error_log(self::LOG . " Successfully built FHIR bundle with " . count($bundle['entry']) . " entries");

        return ['success' => true, 'bundle' => $bundle];
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $dcdId): ?array
    {
        return FhirQuery::first("SELECT dcds.*,
            patients.patient_name, patients.gender, patients.date_of_birth,
            patients.abha_number, patients.abha_address, patients.id as patient_id,
            staff_members.staff_name AS doctor_name,
            staff_members.hpr_id AS doctor_hpr_id,
            staff_members.medical_registration_number AS doctor_reg_number,
            staff_members.registration_council AS doctor_reg_council
            FROM dcds
            INNER JOIN patients ON patients.id = dcds.patient_id
            LEFT JOIN staff_members ON staff_members.id = dcds.consultant_doctor_id
            WHERE dcds.id = ?", 'i', [$dcdId]);
    }

    /** @param array<string, mixed> $visit */
    private function addCommonResources(BundleContext $ctx, array $visit): void
    {
        $this->common->addPatient($ctx, $visit, true);
        $this->common->addOrganization($ctx);
        $this->common->addPractitioner($ctx, $visit);
        $this->common->addAmbulatoryEncounter($ctx);
    }

    /**
     * Adds a MedicationRequest per medicine of every prescription of the visit.
     *
     * @return list<array{reference: string, type: string}> Composition section entries
     */
    private function addAdmissionMedications(BundleContext $ctx, int $dcdId): array
    {
        $requester = $ctx->authorReference();
        $sectionEntries = [];

        error_log(self::LOG . " Fetching admission prescriptions from dcd_medicines");
        $prescriptions = FhirQuery::rows('SELECT id FROM dcd_prescriptions WHERE dcd_id = ?', 'i', [$dcdId]);
        foreach ($prescriptions as $prescription) {
            foreach ($this->loadMedicines($prescription['id']) as $medicine) {
                $name = trim($medicine['medicine_name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $resource = $this->medicationRequest($ctx, $medicine, $name, $requester);
                $ctx->add('MedicationRequest', $resource['id'], $resource);
                $sectionEntries[] = ['reference' => BundleContext::ref('MedicationRequest', $resource['id']), 'type' => 'MedicationRequest'];
            }
        }

        return $sectionEntries;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadMedicines(mixed $prescriptionId): array
    {
        return FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                m.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                m.duration_days,
                mc.condition_name as reason,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM dcd_medicines m
            INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
            LEFT JOIN medical_conditions mc ON m.medical_condition_id = mc.id
            LEFT JOIN dcd_medicine_timing_selections mts ON m.id = mts.dcd_medicine_id
            LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
            WHERE m.dcd_prescription_id = ?
            GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, mc.condition_name, m.duration_days
            ORDER BY m.id", 'i', [$prescriptionId]);
    }

    /**
     * @param array<string, mixed> $medicine
     * @return array<string, mixed>
     */
    private function medicationRequest(BundleContext $ctx, array $medicine, string $name, string $requester): array
    {
        $request = [
            'resourceType' => 'MedicationRequest',
            'id' => abdm_generate_uuid(),
            'status' => 'active',
            'intent' => 'order',
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'authoredOn' => $ctx->now,
            'requester' => ['reference' => $requester],
            'medicationCodeableConcept' => ['text' => $name],
        ];
        $request['dosageInstruction'] = [$this->dosageInstruction($medicine, MedicationDosage::fromRow($medicine))];
        if (!empty($medicine['reason'])) {
            $request['reasonCode'] = [['text' => $medicine['reason']]];
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $medicine
     * @return array<string, mixed>
     */
    private function dosageInstruction(array $medicine, MedicationDosage $dosage): array
    {
        $instruction = [
            'text' => $dosage->text,
            'timing' => [
                'repeat' => [
                    'frequency' => $dosage->frequency,
                    'period' => 1,
                    'periodUnit' => 'd',
                    'when' => $dosage->whenCodes,
                ],
            ],
            'route' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '26643006', 'display' => 'Oral Route']]],
        ];

        if (!empty($medicine['dose_quantity'])) {
            $instruction['doseAndRate'] = [[
                'doseQuantity' => [
                    'value' => (float) $medicine['dose_quantity'],
                    'unit' => 'TAB',
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                    'code' => 'TAB',
                ],
            ]];
        }

        return $instruction;
    }

    /**
     * Attaches the prescription PDF as a Binary resource.
     *
     * @return array{reference: string, type: string}|null the Composition section entry, or null when the PDF failed
     */
    private function addPdfBinary(BundleContext $ctx, int $dcdId): ?array
    {
        error_log(self::LOG . " Generating prescription PDF");
        $pdfResult = $this->pdf->generate($dcdId);

        if (!$pdfResult['success']) {
            error_log(self::LOG . ' PDF generation failed: ' . ($pdfResult['error'] ?? 'unknown error'));
            return null;
        }

        $binaryId = abdm_generate_uuid();
        $ctx->add('Binary', $binaryId, [
            'resourceType' => 'Binary',
            'id' => $binaryId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Binary']],
            'contentType' => 'application/pdf',
            'data' => $pdfResult['pdf_base64'],
        ]);
        error_log(self::LOG . ' PDF attached as Binary: ' . number_format($pdfResult['pdf_size']) . ' bytes');

        return ['reference' => BundleContext::ref('Binary', $binaryId), 'type' => 'Binary'];
    }

    /** @param list<array{reference: string, type: string}> $sectionEntries */
    private function addComposition(BundleContext $ctx, array $sectionEntries, int $dcdId): void
    {
        $coding = [['system' => 'http://snomed.info/sct', 'code' => '440545006', 'display' => 'Prescription record']];
        $compositionId = abdm_generate_uuid();

        $ctx->prepend('Composition', $compositionId, [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord']],
            'identifier' => ['system' => 'https://ndhm.in/phr', 'value' => 'DCD-Prescription-' . $dcdId],
            'status' => 'final',
            'type' => ['coding' => $coding, 'text' => 'DCD Prescription'],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorReference()]],
            'title' => 'DCD Prescription',
            'custodian' => $ctx->organizationReference(),
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>DCD medication record.</p></div>',
            ],
            'section' => [[
                'title' => 'Prescription',
                'code' => ['coding' => $coding],
                'entry' => $sectionEntries,
            ]],
        ]);
    }
}
