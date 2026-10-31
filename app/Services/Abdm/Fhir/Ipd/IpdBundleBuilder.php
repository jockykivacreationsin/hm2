<?php

namespace App\Services\Abdm\Fhir\Ipd;

/**
 * Builds the 3 FHIR R4 "document" Bundles of one IPD admission - one per ABDM care context, because an admission
 * needs 3 SEPARATE hiType-tagged care contexts (ABDM's add-care-context API accepts one hiType per care context):
 *
 *   - dischargeSummary()   hiType DischargeSummary: NRCeS DischargeSummaryRecord, consolidated record for the
 *     whole admission (history, vitals, lab report, medications, procedures and - once discharged - the discharge
 *     note). Also builds for an admission still in progress, with the discharge-specific parts omitted.
 *   - prescription()       hiType Prescription: NRCeS PrescriptionRecord, a CLOSED-slice profile that only allows
 *     MedicationRequest + Binary entries (never DocumentReference).
 *   - diagnosticReport()   hiType DiagnosticReport: NRCeS DiagnosticReportRecord, another CLOSED-slice profile with
 *     at most ONE DiagnosticReport and ONE DocumentReference; all lab results go into that single report.
 *
 * Each method returns ['success' => bool, 'bundle' => array] or ['success' => false, 'error' => string].
 */
final class IpdBundleBuilder
{
    private const NOT_FOUND = ['success' => false, 'error' => 'IPD admission not found'];

    public function __construct(
        private readonly IpdCommonResources $common = new IpdCommonResources(),
        private readonly MedicationEntries $medications = new MedicationEntries(),
        private readonly DiagnosticReportFactory $diagnosticReports = new DiagnosticReportFactory(),
        private readonly DocumentBundle $documentBundle = new DocumentBundle(),
        private readonly PatientFileSections $patientFile = new PatientFileSections(),
        private readonly VitalSignsSection $vitals = new VitalSignsSection(),
        private readonly ProcedureSections $procedures = new ProcedureSections(),
        private readonly DischargeNoteSections $dischargeNote = new DischargeNoteSections(),
        private readonly DischargeSummaryPdf $dischargeSummaryPdf = new DischargeSummaryPdf(),
        private readonly PrescriptionPdf $prescriptionPdf = new PrescriptionPdf(),
        private readonly LabInvestigationsPdf $labInvestigationsPdf = new LabInvestigationsPdf(),
    ) {
    }

    /** @return array{success:bool, error?:string, bundle?:array} */
    public function dischargeSummary(int $ipdId): array
    {
        $ctx = $this->common->load($ipdId);
        if ($ctx === null) {
            return self::NOT_FOUND;
        }

        $docRefId = $this->addDischargeSummaryDocument($ctx);

        $compositionId = abdm_generate_uuid();
        $draft = new SummaryDraft();
        $this->patientFile->collect($ctx, $draft);
        $this->vitals->collect($ctx, $draft);
        $this->addInvestigations($ctx, $draft);
        $this->addMedications($ctx, $draft);
        $this->procedures->collect($ctx, $draft);
        $this->dischargeNote->collect($ctx, $draft);

        $ctx->entries->prepend([
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DischargeSummaryRecord']],
            'status' => 'final',
            // Fixed per the DischargeSummaryRecord profile: the display MUST stay exactly "Discharge summary";
            // the text field carries the human title.
            'type' => FhirCoding::snomed('373942005', 'Discharge summary') + ['text' => 'IPD Discharge Summary'],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorRef()]],
            'title' => 'IPD Discharge Summary',
            'custodian' => ['reference' => $ctx->organizationRef()],
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . $draft->narrativeHtml() . '</div>'],
            'section' => $draft->sections(),
        ]);

        return $this->finish($ctx, 'DischargeSummary');
    }

    /** @return array{success:bool, error?:string, bundle?:array} */
    public function prescription(int $ipdId): array
    {
        $ctx = $this->common->load($ipdId);
        if ($ctx === null) {
            return self::NOT_FOUND;
        }

        $sectionEntries = $this->medications->build($ctx);
        if (empty($sectionEntries)) {
            return ['success' => false, 'error' => 'No medications recorded for this admission'];
        }

        $binaryRef = $this->addPrescriptionBinary($ctx);
        if ($binaryRef !== null) {
            $sectionEntries[] = $binaryRef;
        }

        $ctx->entries->prepend([
            'resourceType' => 'Composition',
            'id' => abdm_generate_uuid(),
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord']],
            'status' => 'final',
            // Fixed per the PrescriptionRecord profile: code and display BOTH fixed.
            'type' => FhirCoding::snomed('440545006', 'Prescription record') + ['text' => 'IPD Prescription'],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorRef()]],
            'title' => 'IPD Prescription',
            'custodian' => ['reference' => $ctx->organizationRef()],
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>IPD medication record.</p></div>'],
            // Exactly ONE section (1..1) per this closed-slice profile.
            'section' => [[
                'title' => 'Prescription',
                'code' => FhirCoding::snomed('440545006', 'Prescription record'),
                'entry' => $sectionEntries,
            ]],
        ]);

        return $this->finish($ctx, 'Prescription');
    }

    /** @return array{success:bool, error?:string, bundle?:array} */
    public function diagnosticReport(int $ipdId): array
    {
        $ctx = $this->common->load($ipdId);
        if ($ctx === null) {
            return self::NOT_FOUND;
        }

        // Legacy quirk: the PDF is rendered even when the admission turns out to have no lab data.
        $labReportPdf = $this->labInvestigationsPdf->render($ipdId);

        // The PDF is not passed to the DiagnosticReport; it is attached separately as a DocumentReference.
        $reportRef = $this->diagnosticReports->build($ctx);
        if ($reportRef === null) {
            return ['success' => false, 'error' => 'No lab investigations recorded for this admission'];
        }

        $sectionEntries = [$reportRef];
        if ($labReportPdf !== null) {
            $sectionEntries[] = $ctx->entries->add($this->labDocumentReference($ctx, $labReportPdf));
        }

        $compositionId = abdm_generate_uuid();
        $ctx->entries->prepend([
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DiagnosticReportRecord']],
            'identifier' => ['system' => 'https://ndhm.in/phr', 'value' => $compositionId],
            'status' => 'final',
            // ABDM's packaging docs for hiType=DiagnosticReport map the document wrapper to SNOMED 721981007 /
            // "Diagnostic Report". NRCeS's value set also has more specific lab concepts, but the ABHA standalone
            // diagnostic viewer is the stricter consumer, so ABDM's published wrapper code is used.
            'type' => FhirCoding::snomed('721981007', 'Diagnostic Report') + ['text' => 'Diagnostic Report- Lab'],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorRef()]],
            'title' => 'Diagnostic Report- Lab',
            'custodian' => ['reference' => $ctx->organizationRef()],
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>IPD lab investigation results.</p></div>'],
            // Exactly ONE section (1..1), closed-slice entry types.
            'section' => [[
                'title' => 'Diagnostic report: Lab',
                'code' => FhirCoding::snomed('721981007', 'Diagnostic Report'),
                'entry' => $sectionEntries,
            ]],
        ]);

        return $this->finish($ctx, 'DiagnosticReport', rewriteToUrnUuid: true);
    }

    /**
     * Renders the discharge summary PDF and adds it as a DocumentReference.
     *
     * @return string|null the DocumentReference id, or null when no PDF could be produced
     */
    private function addDischargeSummaryDocument(IpdContext $ctx): ?string
    {
        $pdf = $this->dischargeSummaryPdf->render($ctx->ipdId);
        if ($pdf === null) {
            return null;
        }

        $id = abdm_generate_uuid();
        $ctx->entries->add([
            'resourceType' => 'DocumentReference',
            'id' => $id,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentReference']],
            'status' => 'current',
            'docStatus' => 'final',
            'type' => FhirCoding::snomed('373942005', 'Discharge Summary') + ['text' => 'Discharge Summary'],
            'subject' => $ctx->patientRef(),
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language' => 'en-IN',
                    'data' => base64_encode($pdf),
                    'title' => 'Discharge Summary',
                    'creation' => $ctx->now,
                ],
            ]],
        ]);

        return $id;
    }

    private function addInvestigations(IpdContext $ctx, SummaryDraft $draft): void
    {
        $reportRef = $this->diagnosticReports->build($ctx);
        if ($reportRef !== null) {
            $draft->section('Investigations', '721981007', 'Diagnostic studies report', [$reportRef]);
        }
    }

    private function addMedications(IpdContext $ctx, SummaryDraft $draft): void
    {
        $entries = $this->medications->build($ctx);
        if (!empty($entries)) {
            $draft->section('Medications', '1003606003', 'Medication history section', $entries);
        }
    }

    /**
     * Renders the prescription PDF and adds it as a Binary.
     *
     * @return array{reference:string, type:string}|null the section reference, or null when the PDF failed
     */
    private function addPrescriptionBinary(IpdContext $ctx): ?array
    {
        $pdf = $this->prescriptionPdf->generate($ctx->ipdId);
        if (!$pdf['success']) {
            error_log('[ABDM][IPD][PRESCRIPTION] PDF generation failed: ' . ($pdf['error'] ?? 'unknown error'));

            return null;
        }

        $ref = $ctx->entries->add([
            'resourceType' => 'Binary',
            'id' => abdm_generate_uuid(),
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Binary']],
            'contentType' => 'application/pdf',
            'data' => $pdf['pdf_base64'],
        ]);
        error_log('[ABDM][IPD][PRESCRIPTION] PDF attached as Binary: ' . number_format($pdf['pdf_size']) . ' bytes, base64 length: ' . strlen($pdf['pdf_base64']));

        return $ref + ['type' => 'Binary'];
    }

    /** The lab PDF as its own DocumentReference (like OPD does), which the ABHA PHR app renders better. */
    private function labDocumentReference(IpdContext $ctx, string $pdf): array
    {
        return [
            'resourceType' => 'DocumentReference',
            'id' => abdm_generate_uuid(),
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentReference']],
            'status' => 'current',
            'type' => FhirCoding::snomed('721981007', 'Diagnostic studies report') + ['text' => 'IPD Lab Investigations'],
            'subject' => $ctx->patientRef(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorRef()]],
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language' => 'en-IN',
                    'data' => base64_encode($pdf),
                    'title' => 'IPD Lab Investigations',
                    'creation' => $ctx->now,
                ],
            ]],
        ];
    }

    /** @return array{success:true, bundle:array} */
    private function finish(IpdContext $ctx, string $identifierSuffix, bool $rewriteToUrnUuid = false): array
    {
        $entries = $ctx->entries->all();
        if ($rewriteToUrnUuid) {
            $entries = $this->documentBundle->rewriteToUrnUuid($entries);
        }

        return [
            'success' => true,
            'bundle' => $this->documentBundle->wrap($ctx->ipdId, $identifierSuffix, FhirTime::now(), $entries),
        ];
    }
}
