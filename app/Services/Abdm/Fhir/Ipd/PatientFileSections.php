<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * History sections of the discharge summary, from the admission's patient file (ipd_patient_files):
 * chief complaints, admission diagnosis, history of present illness, allergies, past and family history.
 */
final class PatientFileSections
{
    public function collect(IpdContext $ctx, SummaryDraft $draft): void
    {
        $file = FhirQuery::first("SELECT * FROM ipd_patient_files WHERE ipd_id = ?", 'i', [$ctx->ipdId]);
        if (!$file) {
            return;
        }

        $this->chiefComplaints($ctx, $draft, $this->plainText($file, 'chief_complaints'));
        $this->admissionDiagnosis($ctx, $draft, $this->plainText($file, 'provisional_diagnosis'));
        $this->historyOfPresentIllness($ctx, $draft, $this->plainText($file, 'history_of_present_illness'));
        $this->allergies($ctx, $draft, $this->plainText($file, 'allergies'));
        $this->pastHistory($ctx, $draft, $this->plainText($file, 'past_medical_history'));
        $this->familyHistory($draft, $this->plainText($file, 'family_ho'));
    }

    private function plainText(array $file, string $column): string
    {
        return trim(strip_tags((string) ($file[$column] ?? '')));
    }

    private function chiefComplaints(IpdContext $ctx, SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $ref = $ctx->entries->add($this->diagnosisCondition($ctx, 'confirmed', 'Confirmed', $text));
        $draft->section('Chief Complaints', '422843007', 'Chief complaint section', [$ref]);
        $draft->narrate('Chief Complaints', $text);
    }

    private function admissionDiagnosis(IpdContext $ctx, SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $ref = $ctx->entries->add($this->diagnosisCondition($ctx, 'provisional', 'Provisional', $text));
        $draft->section('Admission Diagnosis', '148006', 'Preliminary diagnosis', [$ref]);
        $draft->narrate('Admission Diagnosis', $text);
    }

    private function diagnosisCondition(IpdContext $ctx, string $verificationCode, string $verificationDisplay, string $text): array
    {
        return [
            'resourceType' => 'Condition',
            'id' => abdm_generate_uuid(),
            'clinicalStatus' => FhirCoding::concept(FhirCoding::CONDITION_CLINICAL, 'active', 'Active'),
            'verificationStatus' => FhirCoding::concept(FhirCoding::CONDITION_VERIFICATION, $verificationCode, $verificationDisplay),
            'category' => FhirCoding::encounterDiagnosis(),
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'code' => ['text' => $text],
            'recordedDate' => $ctx->admittedAt(),
        ];
    }

    private function historyOfPresentIllness(IpdContext $ctx, SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $ref = $ctx->entries->add([
            'resourceType' => 'Condition',
            'id' => abdm_generate_uuid(),
            'clinicalStatus' => FhirCoding::concept(FhirCoding::CONDITION_CLINICAL, 'active', 'Active'),
            'category' => FhirCoding::encounterDiagnosis(),
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'code' => ['text' => 'History of Present Illness'],
            'note' => [['text' => $text]],
            'recordedDate' => $ctx->admittedAt(),
        ]);
        // The NRCeS DischargeSummaryRecord allows only ONE section with the Chief complaint code (ChiefComplaints
        // slice 0..1), so the history joins that section as a further entry instead of becoming a second section.
        if (!$draft->appendToSection('Chief Complaints', [$ref])) {
            $draft->section('Chief Complaints', '422843007', 'Chief complaint section', [$ref]);
        }
        $draft->narrate('History of Present Illness', $text);
    }

    private function allergies(IpdContext $ctx, SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $ref = $ctx->entries->add([
            'resourceType' => 'AllergyIntolerance',
            'id' => abdm_generate_uuid(),
            'clinicalStatus' => FhirCoding::concept('http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical', 'active', 'Active'),
            'verificationStatus' => FhirCoding::concept('http://terminology.hl7.org/CodeSystem/allergyintolerance-verification', 'confirmed', 'Confirmed'),
            'patient' => $ctx->patientRef(),
            'recordedDate' => $ctx->now,
            'code' => ['text' => $text],
        ]);
        $draft->section('Allergies', '722446000', 'Allergy record', [$ref]);
    }

    private function pastHistory(IpdContext $ctx, SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $ref = $ctx->entries->add([
            'resourceType' => 'Condition',
            'id' => abdm_generate_uuid(),
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'code' => ['text' => $text],
        ]);
        $draft->section('Medical History', '1003642006', 'Past medical history section', [$ref]);
    }

    /** Family history has no resource of its own; it is carried as the section's narrative. */
    private function familyHistory(SummaryDraft $draft, string $text): void
    {
        if ($text === '') {
            return;
        }

        $draft->addSection([
            'title' => 'Family History',
            'code' => FhirCoding::snomed('422432008', 'Family history section'),
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>'],
        ]);
    }
}
