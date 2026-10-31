<?php

namespace App\Services\Abdm\Fhir\Ipd;

/**
 * The discharge-only sections: final diagnosis and follow-up instructions (from the ipd_discharge row).
 * Nothing is added while the patient is still admitted.
 */
final class DischargeNoteSections
{
    public function collect(IpdContext $ctx, SummaryDraft $draft): void
    {
        if (!$ctx->isDischarged()) {
            return;
        }

        $this->dischargeDiagnosis($ctx, $draft);
        $this->followUp($ctx, $draft);
    }

    private function dischargeDiagnosis(IpdContext $ctx, SummaryDraft $draft): void
    {
        if (empty($ctx->visit['discharge_diagnosis'])) {
            return;
        }
        $diagnosis = trim(strip_tags($ctx->visit['discharge_diagnosis']));
        if ($diagnosis === '') {
            return;
        }

        $ref = $ctx->entries->add([
            'resourceType' => 'Condition',
            'id' => abdm_generate_uuid(),
            'clinicalStatus' => FhirCoding::concept(FhirCoding::CONDITION_CLINICAL, 'resolved', 'Resolved'),
            'verificationStatus' => FhirCoding::concept(FhirCoding::CONDITION_VERIFICATION, 'confirmed', 'Confirmed'),
            'category' => FhirCoding::encounterDiagnosis(),
            'code' => FhirCoding::snomed('397659008', 'Final diagnosis') + ['text' => $diagnosis],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'recordedDate' => $ctx->dischargedAt(),
        ]);
        $draft->section('Discharge Diagnosis', '397659008', 'Final diagnosis', [$ref]);
        $draft->narrate('Discharge Diagnosis', $diagnosis);
    }

    /**
     * Follow-up instructions from condition_on_discharge, follow_up_detail and urgent_care_condition.
     *
     * Carried in the Composition narrative only - NOT as a CarePlan resource with its own section. The ABHA PHR app
     * fails the whole record ("Error in parsing the data") once the bundle contains that CarePlan, which only exists
     * after discharge; confirmed by bisecting a real push (everything else in the bundle opens fine). A text section
     * is not an alternative: the NRCeS DischargeSummaryRecord allows only one section per code, and every code
     * suited to it is already taken. The same text is also printed in the attached discharge summary PDF.
     */
    private function followUp(IpdContext $ctx, SummaryDraft $draft): void
    {
        $visit = $ctx->visit;
        $followUp = trim(strip_tags($visit['follow_up_detail'] ?? ''));
        $condition = trim($visit['condition_on_discharge'] ?? '');
        $urgentCare = trim($visit['urgent_care_condition'] ?? '');
        $note = trim(strip_tags($visit['discharge_note'] ?? ''));

        foreach (['Condition on Discharge' => $condition, 'Follow-up' => $followUp, 'Urgent Care' => $urgentCare, 'Note' => $note] as $label => $value) {
            if ($value !== '') {
                $draft->narrate($label, $value);
            }
        }
    }
}
