<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * hiType Prescription: medications only (opd_prescriptions + opd_medicines) plus the prescription PDF.
 *
 * NRCeS's PrescriptionRecord profile is a CLOSED slice: only MedicationRequest and/or Binary entries are
 * allowed, NOT Observation or DiagnosticReport.
 */
final class PrescriptionBuilder
{
    private const TITLE = 'Prescription';

    private const SNOMED_CODE = '440545006';

    private const SNOMED_DISPLAY = 'Prescription record';

    public function __construct(
        private readonly OpdBundleAssembler $assembler,
        private readonly MedicationRequestFactory $medications,
        private readonly OpdPrescriptionPdf $prescriptionPdf,
    ) {
    }

    /** @return array{success: bool, bundle: array<string, mixed>} */
    public function build(OpdBundleContext $context): array
    {
        if (!$this->hasPrescribedMedicines($context->opdId)) {
            error_log("[ABDM][OPD][PRESCRIPTION] No prescription data found for OPD #{$context->opdId} - returning placeholder bundle");

            return $this->assembler->placeholder($context, 'Prescription', self::TITLE, self::SNOMED_CODE, self::SNOMED_DISPLAY);
        }

        $context->startDocument();

        [$medicationRefs, $findings] = $this->addMedications($context);
        $sections = empty($medicationRefs) ? [] : [$this->prescriptionSection($medicationRefs)];
        $sections = $this->attachPdf($context, $sections);

        $composition = $this->assembler->composition(
            $context,
            self::TITLE,
            $this->assembler->snomed(self::SNOMED_CODE, self::SNOMED_DISPLAY)['coding'],
            $this->narrative($findings),
            $sections,
        );

        return $this->assembler->finish($context, $composition);
    }

    private function hasPrescribedMedicines(int $opdId): bool
    {
        $row = FhirQuery::first("SELECT COUNT(*) as cnt FROM opd_prescriptions p 
            INNER JOIN opd_medicines m ON m.opd_prescription_id = p.id 
            WHERE p.opd_id = ?", 'i', [$opdId]);

        return (int) ($row['cnt'] ?? 0) !== 0;
    }

    /**
     * Adds one MedicationRequest per medicine of every prescription of the visit.
     *
     * @return array{0: array<int, array{reference: string}>, 1: string[]} section references, prescription findings
     */
    private function addMedications(OpdBundleContext $context): array
    {
        $prescriptions = FhirQuery::rows("SELECT id, doctor_id, findings FROM opd_prescriptions WHERE opd_id = ?", 'i', [$context->opdId]);

        $refs = [];
        $findings = [];
        foreach ($prescriptions as $prescription) {
            if (!empty($prescription['findings'])) {
                $findings[] = $prescription['findings'];
            }
            foreach ($this->medicines((int) $prescription['id']) as $medicine) {
                $medId = abdm_generate_uuid();
                $request = $this->medications->build($context, $medId, $medicine, $prescription);
                $refs[] = $context->add('MedicationRequest', $medId, $request);
            }
        }

        return [$refs, $findings];
    }

    /** @return array<int, array<string, mixed>> */
    private function medicines(int $prescriptionId): array
    {
        return FhirQuery::rows("
                SELECT 
                    pp.product_name as medicine_name,
                    m.dose_quantity,
                    ft.display_text as dose_interval,
                    mr.relation_name as meal_relation,
                    m.duration_days,
                    m.medical_condition_id,
                    mc.condition_name,
                    mc.snomed_code,
                    mc.snomed_display,
                    GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names,
                    '' as remarks
                FROM opd_medicines m
                INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
                LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
                LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
                LEFT JOIN opd_medicine_timing_selections mts ON m.id = mts.opd_medicine_id
                LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
                LEFT JOIN medical_conditions mc ON m.medical_condition_id = mc.id
                WHERE m.opd_prescription_id = ?
                GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, m.duration_days, m.medical_condition_id, mc.condition_name, mc.snomed_code, mc.snomed_display
                ORDER BY m.id
            ", 'i', [$prescriptionId]);
    }

    /**
     * @param array<int, array<string, string>> $references
     * @return array<string, mixed>
     */
    private function prescriptionSection(array $references): array
    {
        return $this->assembler->entrySection(
            'Prescription',
            $this->assembler->snomed('721912009', 'Medication summary document'),
            $references,
        );
    }

    /**
     * Attaches the prescription PDF as a Binary entry, referenced from the first Composition section
     * (the section is created when the prescription has no medicines).
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function attachPdf(OpdBundleContext $context, array $sections): array
    {
        $pdf = $this->prescriptionPdf->render($context->opdId);
        if (!$pdf['success']) {
            error_log('[ABDM][OPD][PRESCRIPTION] PDF generation failed: ' . ($pdf['error'] ?? 'unknown error'));

            return $sections;
        }

        $binaryId = abdm_generate_uuid();
        $context->add('Binary', $binaryId, [
            'resourceType' => 'Binary',
            'id' => $binaryId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Binary'],
            ],
            'contentType' => 'application/pdf',
            'data' => $pdf['pdf_base64'],
        ]);

        if (empty($sections)) {
            $sections[] = $this->prescriptionSection([]);
        }
        $sections[0]['entry'][] = ['reference' => 'Binary/' . $binaryId, 'type' => 'Binary'];

        error_log('[ABDM][OPD][PRESCRIPTION] PDF attached as Binary: ' . number_format($pdf['pdf_size']) . ' bytes, base64 length: ' . strlen($pdf['pdf_base64']));

        return $sections;
    }

    /** @param string[] $findings */
    private function narrative(array $findings): string
    {
        if (empty($findings)) {
            return Xhtml::div('<p>Prescription record.</p>');
        }

        return Xhtml::div('<p><b>Findings:</b> ' . Xhtml::escape(implode('; ', $findings)) . '</p>');
    }
}
