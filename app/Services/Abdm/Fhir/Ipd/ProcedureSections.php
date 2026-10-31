<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;

/** The discharge summary's Procedures section: procedure services of the stay plus the discharge note's procedures. */
final class ProcedureSections
{
    public function collect(IpdContext $ctx, SummaryDraft $draft): void
    {
        $refs = $this->serviceProcedures($ctx);

        if ($ctx->isDischarged()) {
            array_push($refs, ...$this->dischargeProcedures($ctx));
        }

        if (!empty($refs)) {
            $draft->section('Procedures', '371525003', 'Clinical procedure report', $refs);
        }
    }

    /** Procedures billed as ipd_services of category 'Procedure'. */
    private function serviceProcedures(IpdContext $ctx): array
    {
        $refs = [];
        $procedures = FhirQuery::rows("SELECT ipd_services.id, ipd_services.service_title, ipd_services.service_date_time, ipd_services.status
            FROM ipd_services INNER JOIN charge_departments ON charge_departments.id = ipd_services.charge_department_id
            WHERE ipd_services.ipd_id = ? AND charge_departments.category = 'Procedure'", 'i', [$ctx->ipdId]);

        foreach ($procedures as $procedure) {
            $items = FhirQuery::rows("SELECT charges.charge_name FROM ipd_service_detail
                INNER JOIN charges ON charges.id = ipd_service_detail.charge_id
                WHERE ipd_service_detail.ipd_service_id = ?", 'i', [$procedure['id']]);
            $itemNames = array_column($items, 'charge_name');

            $refs[] = $ctx->entries->add([
                'resourceType' => 'Procedure',
                'id' => abdm_generate_uuid(),
                'status' => (strtolower((string) $procedure['status']) === 'completed') ? 'completed' : 'in-progress',
                'subject' => $ctx->patientRef(),
                'encounter' => $ctx->encounterRef(),
                'code' => ['text' => $procedure['service_title'] . (!empty($itemNames) ? ' (' . implode(', ', $itemNames) . ')' : '')],
                'performedDateTime' => FhirTime::utc($procedure['service_date_time']),
            ]);
        }

        return $refs;
    }

    /** Free-text procedures of the discharge note, performed during the whole admission. */
    private function dischargeProcedures(IpdContext $ctx): array
    {
        $refs = [];
        $rows = FhirQuery::rows("SELECT procedure_detail FROM ipd_discharge_procedures WHERE discharge_id = ?", 'i', [$ctx->visit['discharge_id']]);

        foreach ($rows as $row) {
            $detail = trim(strip_tags($row['procedure_detail']));
            if ($detail === '') {
                continue;
            }

            $refs[] = $ctx->entries->add([
                'resourceType' => 'Procedure',
                'id' => abdm_generate_uuid(),
                'status' => 'completed',
                'subject' => $ctx->patientRef(),
                'encounter' => $ctx->encounterRef(),
                'code' => ['text' => $detail],
                'performedPeriod' => ['start' => $ctx->admittedAt(), 'end' => $ctx->dischargedAt()],
            ]);
        }

        return $refs;
    }
}
