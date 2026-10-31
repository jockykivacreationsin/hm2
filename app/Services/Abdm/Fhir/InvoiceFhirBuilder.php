<?php

namespace App\Services\Abdm\Fhir;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use App\Models\BillingCharge;
use App\Models\Dcd;
use App\Models\Ipd;
use App\Models\Opd;
use App\Services\Pdf\IpdReportData;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * Builds a FHIR R4 Invoice bundle for ABDM HI type "Invoice".
 *
 * Source: billing_charges (one row per invoice_number per visit) joined with
 * billing_charges_detail → charges for line items.
 *
 * One care context = one visit (OPD/IPD/DCD). If the visit has multiple
 * invoice_numbers they are ALL included in a single FHIR Bundle.
 */
class InvoiceFhirBuilder
{
    use FhirBundleTrait;

    protected function kind(): string
    {
        return 'INVOICE';
    }

    /**
     * Build a FHIR Invoice bundle for the given visit.
     *
     * @param string $module  'OPD' | 'IPD' | 'DCD'
     * @param int    $id      visit primary key
     * @param string|null $dateFrom  consent range start (ISO-8601 UTC)
     * @param string|null $dateTo    consent range end   (ISO-8601 UTC)
     * @return array|null  FHIR Bundle or null when nothing to share
     */
    public function buildBundle(string $module, int $id, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $module = strtoupper($module);
        if (!in_array($module, ['OPD', 'IPD', 'DCD'], true)) {
            return null;
        }

        // Load the visit row to get the patient and dates needed for the encounter resource.
        $visitTable = strtolower($module) . 's';
        $visit = DB::table($visitTable)->where('id', $id)->first();
        if (!$visit) {
            return null;
        }

        $patient = DB::table('patients')->where('id', $visit->patient_id ?? null)->first();
        if (!$patient) {
            return null;
        }

        // Load all non-cancelled invoices for this visit directly from billing_charges.
        $invoices = DB::table('billing_charges as bc')
            ->selectRaw("
                bc.invoice_number,
                UPPER(bc.module)                              AS module,
                bc.module_id,
                ? AS patient_id,
                MIN(bc.charge_date_time)                      AS invoice_date_time,
                IF(SUM(bc.invoice_status = 'issued') = 0, 'cancelled', 'issued') AS invoice_status,
                'INR'                                         AS currency,
                SUM(bc.amount)                                AS gross_total,
                SUM(bc.amount - bc.total)                     AS discount_total,
                SUM(bc.total)                                 AS net_total,
                SUM(bc.paid_amount)                           AS paid_total,
                MIN(bc.created_on)                            AS created_on
            ", [$visit->patient_id])
            ->whereRaw('UPPER(bc.module) = ?', [$module])
            ->where('bc.module_id', $id)
            ->groupBy('bc.invoice_number', DB::raw('UPPER(bc.module)'), 'bc.module_id')
            ->havingRaw("SUM(bc.invoice_status = 'issued') > 0")
            ->get();

        if ($invoices->isEmpty()) {
            return null;
        }

        // Filter by consent date range (invoice_date_time is the MIN charge_date_time).
        $bounds = $this->bounds($dateFrom, $dateTo);
        $visitStart = match ($module) {
            'OPD' => $visit->opd_date_time,
            'IPD' => $visit->admission_date_time,
            'DCD' => $visit->dcd_date_time,
        };
        $visitEnd = match ($module) {
            'IPD' => DB::table('ipd_discharge')->where('ipd_id', $id)->max('discharge_date_time') ?: now()->toDateTimeString(),
            'DCD' => DB::table('dcd_discharge')->where('dcd_id', $id)->max('discharge_date_time') ?: now()->toDateTimeString(),
            default => $visitStart,
        };
        if (!$this->encounterInConsentRange($visitStart, $visitEnd, $bounds)) {
            return null;
        }

        // Shared resources: Patient, Organization.
        $patientRes      = $this->buildPatientResource($patient);
        $organizationRes = $this->buildOrganizationResource();

        // Doctor (optional) — consultant_doctor_id on the visit row.
        $doctorId    = $visit->consultant_doctor_id ?? null;
        $doctorRow   = $doctorId ? DB::table('staff_members')->where('id', $doctorId)->first() : null;
        $practitionerRes = $this->buildPractitionerResource($doctorRow);

        $context = [
            'patient'      => $patientRes['id'],
            'organization' => $organizationRes['id'],
            'practitioner' => $practitionerRes ? $practitionerRes['id'] : null,
            'encounter'    => (string) Str::uuid(),
        ];

        $entries  = [$patientRes, $organizationRes];
        if ($practitionerRes) {
            $entries[] = $practitionerRes;
        }
        $entries[] = $this->buildEncounterForModule($visit, $module, $context);

        $invoiceResources = [];
        $allChargeItems   = [];
        foreach ($invoices as $inv) {
            $lineItems = $this->loadLineItems((string) $inv->invoice_number, $module, $id);
            $built = $this->buildInvoiceResource($inv, $lineItems, $context, $module);
            $invoiceResources[] = $built['invoice'];
            foreach ($built['chargeItems'] as $ci) {
                $allChargeItems[] = $ci;
            }
        }

        if (!$invoiceResources) {
            return null;
        }

        // Generate single consolidated bill PDF (detailed discharge bill for IPD/DCD, consolidated bill for OPD)
        $pdfArtifacts = $this->buildPdfDocumentReference($module, $id, $context, $invoiceResources);

        // NRCeS InvoiceRecord: Exactly ONE section ("Invoice Details"), containing references only to Invoice resources.
        // ChargeItem resources are linked by Invoice.lineItem[].chargeItemReference in Bundle.entry.
        // DocumentReference and Binary resources live in Bundle.entry for PDF attachment viewing/download.
        $invoiceSectionEntries = [];
        foreach ($invoiceResources as $inv) {
            $invoiceSectionEntries[] = [
                'reference' => 'Invoice/' . $inv['id'],
                'type'      => 'Invoice',
            ];
        }

        $invoiceType = match ($module) {
            'OPD'   => ['code' => '00', 'display' => 'Consultation'],
            'IPD'   => ['code' => '02', 'display' => 'Hospital'],
            'DCD'   => ['code' => '02', 'display' => 'Hospital'],
            default => ['code' => '99', 'display' => 'Others'],
        };

        $sections = [
            [
                'title' => 'Invoice Details',
                'code'  => [
                    'coding' => [[
                        'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-billing-codes',
                        'code'    => $invoiceType['code'],
                        'display' => $invoiceType['display'],
                    ]],
                    'text' => 'Invoice Details',
                ],
                'entry' => $invoiceSectionEntries,
            ],
        ];

        $compositionId = (string) Str::uuid();
        $composition = [
            'resourceType' => 'Composition',
            'id'           => $compositionId,
            'meta'         => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/InvoiceRecord'],
            ],
            'identifier'   => ['system' => 'https://ndhm.in/phr', 'value' => "{$module}-Invoice-{$id}"],
            'status'       => 'final',
            'type'         => [
                'coding' => [[
                    'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-clinical-document-type-code',
                    'code'    => 'AR',
                    'display' => 'Invoice Record',
                ]],
                'text' => 'Invoice Record',
            ],
            'subject'      => ['reference' => 'Patient/' . $context['patient']],
            'encounter'    => ['reference' => 'Encounter/' . $context['encounter']],
            'date'         => CarbonImmutable::now()->toIso8601String(),
            'author'       => [['reference' => ($context['practitioner'] ? 'Practitioner/' . $context['practitioner'] : 'Organization/' . $context['organization'])]],
            'title'        => $module . ' Invoice Record',
            'custodian'    => ['reference' => 'Organization/' . $context['organization']],
            'text'         => $this->narrative($module . ' Invoice Record'),
            'section'      => $sections,
        ];

        $rawResources = array_merge([$composition], $entries, $invoiceResources, $allChargeItems);
        if ($pdfArtifacts !== null) {
            $rawResources[] = $pdfArtifacts['docRef'];
            $rawResources[] = $pdfArtifacts['binary'];
        }

        $bundleEntries = array_map(function ($r) {
            return [
                'fullUrl'  => $r['resourceType'] . '/' . $r['id'],
                'resource' => $r,
            ];
        }, $rawResources);

        $now = CarbonImmutable::now()->toIso8601String();
        return [
            'resourceType' => 'Bundle',
            'id'           => (string) Str::uuid(),
            'meta'         => [
                'versionId'   => '1',
                'profile'     => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle'],
                'lastUpdated' => $now,
            ],
            'identifier'   => ['system' => 'https://ndhm.in', 'value' => "{$module}-Invoice-{$id}"],
            'type'         => 'document',
            'timestamp'    => $now,
            'entry'        => $bundleEntries,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildEncounterForModule(object $visit, string $module, array $context): array
    {
        $discharge = match ($module) {
            'IPD' => DB::table('ipd_discharge')->where('ipd_id', $visit->id)->first(),
            'DCD' => DB::table('dcd_discharge')->where('dcd_id', $visit->id)->first(),
            default => null,
        };
        $start = match ($module) {
            'OPD' => $visit->opd_date_time ?? null,
            'IPD' => $visit->admission_date_time ?? null,
            'DCD' => $visit->dcd_date_time ?? null,
        };
        $end = $module === 'OPD' ? $start : ($discharge?->discharge_date_time ?? null);

        $resource = [
            'resourceType'    => 'Encounter',
            'id'              => $context['encounter'],
            'status'          => $module === 'OPD' || $discharge ? 'finished' : 'in-progress',
            'class'           => [
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code'    => $module === 'OPD' ? 'AMB' : 'IMP',
                'display' => $module === 'OPD' ? 'ambulatory' : 'inpatient encounter',
            ],
            'subject'         => ['reference' => 'Patient/' . $context['patient']],
            'serviceProvider' => ['reference' => 'Organization/' . $context['organization']],
        ];

        if ($start) {
            $resource['period']['start'] = $this->iso($start);
        }
        if ($end) {
            $resource['period']['end'] = $this->iso($end);
        }
        if (!empty($context['practitioner'])) {
            $resource['participant'] = [['individual' => ['reference' => 'Practitioner/' . $context['practitioner']]]];
        }

        return $resource;
    }

    /** @return array[] billing_charges_detail rows with their charge_name */
    private function loadLineItems(string $invoiceNumber, string $module, int $moduleId): array
    {
        $rows = DB::table('billing_charges_detail as bcd')
            ->join('billing_charges as bc', 'bc.id', '=', 'bcd.module_charge_id')
            ->join('charges as c', 'c.id', '=', 'bcd.charge_id')
            ->leftJoin('charge_departments as cd', 'cd.id', '=', 'bc.charge_department_id')
            ->where('bc.invoice_number', $invoiceNumber)
            ->whereRaw('UPPER(bc.module) = ?', [$module])
            ->where('bc.module_id', $moduleId)
            ->select(
                'c.charge_name',
                'cd.category as department_category',
                'bcd.amount',
                'bcd.discount_percentage',
                'bcd.discount_amount',
                'bcd.quantity',
                'bcd.total',
                'bcd.tax_percentage',
                'bcd.tax_amount',
            )
            ->get()
            ->all();

        if (empty($rows)) {
            // Fallback: in case billing_charges has rows without billing_charges_detail
            $charges = DB::table('billing_charges as bc')
                ->leftJoin('charge_departments as cd', 'cd.id', '=', 'bc.charge_department_id')
                ->where('bc.invoice_number', $invoiceNumber)
                ->whereRaw('UPPER(bc.module) = ?', [$module])
                ->where('bc.module_id', $moduleId)
                ->where('bc.invoice_status', 'issued')
                ->select(
                    'bc.charge_title as charge_name',
                    'cd.category as department_category',
                    'bc.amount',
                    'bc.discount as discount_amount',
                    'bc.total',
                )
                ->get();
            foreach ($charges as $c) {
                $rows[] = (object) [
                    'charge_name'         => $c->charge_name,
                    'department_category' => $c->department_category,
                    'amount'              => $c->amount,
                    'discount_percentage' => 0,
                    'discount_amount'     => $c->discount_amount,
                    'quantity'            => 1,
                    'total'               => $c->total,
                    'tax_percentage'      => 0,
                    'tax_amount'          => 0,
                ];
            }
        }

        return $rows;
    }

    private function billingCodeForCategory(?string $category): array
    {
        return match (strtolower((string) $category)) {
            'lab', 'pathology'     => ['code' => '04', 'display' => 'Pathology'],
            'procedure', 'ipd'     => ['code' => '02', 'display' => 'IPD'],
            'pharmacy', 'medicine' => ['code' => '01', 'display' => 'Pharmacy'],
            'opd', 'consultation'  => ['code' => '00', 'display' => 'Consultation'],
            default                => ['code' => '99', 'display' => 'Others'],
        };
    }

    /**
     * @param object $inv
     * @param array $lineItems
     * @param array $context
     * @return array{invoice: array<string, mixed>, chargeItems: list<array<string, mixed>>}
     */
    private function buildInvoiceResource(object $inv, array $lineItems, array $context, string $module = 'DCD'): array
    {
        $invoiceId = (string) Str::uuid();
        $chargeItemResources = [];
        $lineItemArray = [];
        $seq = 1;

        foreach ($lineItems as $li) {
            $qty = (float) ($li->quantity ?? 1);
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $chargeItemId = (string) Str::uuid();
            $itemName = $this->clean($li->charge_name) ?: 'Hospital Service';
            $billing = $this->billingCodeForCategory($li->department_category ?? null);

            // NRCeS ChargeItem resource — ABHA app parses productCodeableConcept for Item Name and quantity for Item Quantity
            $chargeItem = [
                'resourceType' => 'ChargeItem',
                'id'           => $chargeItemId,
                'meta'         => [
                    'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/ChargeItem'],
                ],
                'status'       => 'billed',
                'code'         => [
                    'coding' => [[
                        'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-billing-codes',
                        'code'    => $billing['code'],
                        'display' => $billing['display'],
                    ]],
                    'text' => $itemName,
                ],
                'productCodeableConcept' => [
                    'coding' => [[
                        'system'  => 'http://snomed.info/sct',
                        'code'    => match (strtolower((string) ($li->department_category ?? ''))) {
                            'lab', 'pathology'     => '721981007',
                            'procedure', 'ipd'     => '387713003',
                            'pharmacy', 'medicine' => '373873005',
                            'opd', 'consultation'  => '408443003',
                            default                => '363787002',
                        },
                        'display' => $itemName,
                    ]],
                    'text' => $itemName,
                ],
                'subject'            => ['reference' => 'Patient/' . $context['patient']],
                'context'            => ['reference' => 'Encounter/' . $context['encounter']],
                'occurrenceDateTime' => $this->iso($inv->invoice_date_time) ?? CarbonImmutable::now()->toIso8601String(),
                'quantity'     => [
                    'value' => $qty,
                    'unit'  => 'Unit',
                ],
                'priceOverride' => [
                    'value'    => (float) ($li->amount ?? 0),
                    'currency' => 'INR',
                ],
            ];
            if (!empty($context['practitioner'])) {
                $chargeItem['performer'] = [['actor' => ['reference' => 'Practitioner/' . $context['practitioner']]]];
            }
            $chargeItemResources[] = $chargeItem;

            $lineItemArray[] = [
                'sequence'            => $seq++,
                'chargeItemReference' => [
                    'reference' => 'ChargeItem/' . $chargeItemId,
                    'display'   => $itemName,
                ],
                'priceComponent'      => array_values(array_filter([
                    [
                        'type' => 'base',
                        'code' => [
                            'coding' => [[
                                'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-price-components',
                                'code'    => '01',
                                'display' => 'Rate',
                            ]],
                        ],
                        'amount' => ['value' => (float) ($li->amount ?? 0), 'currency' => 'INR'],
                    ],
                    (float) ($li->discount_amount ?? 0) > 0
                        ? [
                            'type' => 'deduction',
                            'code' => [
                                'coding' => [[
                                    'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-price-components',
                                    'code'    => '02',
                                    'display' => 'Discount',
                                ],],
                            ],
                            'factor' => (float) ($li->discount_percentage ?? 0) / 100,
                            'amount' => ['value' => -(float) ($li->discount_amount), 'currency' => 'INR'],
                        ]
                        : null,
                    (float) ($li->tax_amount ?? 0) > 0
                        ? [
                            'type' => 'tax',
                            'code' => [
                                'coding' => [[
                                    'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-price-components',
                                    'code'    => '03',
                                    'display' => 'CGST',
                                ],],
                            ],
                            'factor' => (float) ($li->tax_percentage ?? 0) / 100,
                            'amount' => ['value' => (float) ($li->tax_amount), 'currency' => 'INR'],
                        ]
                        : null,
                ])),
            ];
        }

        $invoiceType = match ($module) {
            'OPD'   => ['code' => '00', 'display' => 'Consultation'],
            'IPD'   => ['code' => '02', 'display' => 'Hospital'],
            'DCD'   => ['code' => '02', 'display' => 'Hospital'],
            default => ['code' => '99', 'display' => 'Others'],
        };

        $invoiceResource = [
            'resourceType' => 'Invoice',
            'id'           => $invoiceId,
            'meta'         => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Invoice'],
            ],
            'status'       => 'issued',
            'type'         => [
                'coding' => [[
                    'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-billing-codes',
                    'code'    => $invoiceType['code'],
                    'display' => $invoiceType['display'],
                ]],
                'text' => $invoiceType['display'],
            ],
            'subject'      => ['reference' => 'Patient/' . $context['patient']],
            'recipient'    => ['reference' => 'Patient/' . $context['patient']],
            'date'         => $this->iso($inv->invoice_date_time) ?? CarbonImmutable::now()->toIso8601String(),
            'issuer'       => ['reference' => 'Organization/' . $context['organization']],
            'identifier'   => [['system' => 'https://ndhm.in/invoice', 'value' => (string) $inv->invoice_number]],
            'totalNet'     => ['value' => (float) ($inv->net_total ?? $inv->gross_total ?? 0), 'currency' => 'INR'],
            'totalGross'   => ['value' => (float) ($inv->gross_total ?? $inv->net_total ?? 0), 'currency' => 'INR'],
        ];

        if (!empty($context['practitioner'])) {
            $invoiceResource['participant'] = [['actor' => ['reference' => 'Practitioner/' . $context['practitioner']]]];
        }

        if ($lineItemArray) {
            $invoiceResource['lineItem'] = $lineItemArray;
        }

        return [
            'invoice'     => $invoiceResource,
            'chargeItems' => $chargeItemResources,
        ];
    }

    /**
     * Builds DocumentReference and Binary resources wrapping the consolidated bill PDF.
     *
     * @param string $module
     * @param int $id
     * @param array $context
     * @param array $invoiceResources
     * @return array{docRef: array<string, mixed>, binary: array<string, mixed>}|null
     */
    private function buildPdfDocumentReference(string $module, int $id, array $context, array $invoiceResources = []): ?array
    {
        $pdfBytes = $this->generateInvoicePdf($module, $id);
        if (!$pdfBytes) {
            return null;
        }

        $docRefId = (string) Str::uuid();
        $binaryId = (string) Str::uuid();
        $title = "{$module} Invoice Bill #{$id}";
        $now = CarbonImmutable::now()->toIso8601String();

        $billingCode = match ($module) {
            'OPD'   => ['code' => '00', 'display' => 'Consultation'],
            'IPD'   => ['code' => '02', 'display' => 'IPD'],
            'DCD'   => ['code' => '02', 'display' => 'IPD'],
            default => ['code' => '99', 'display' => 'Others'],
        };

        $docRef = [
            'resourceType' => 'DocumentReference',
            'id'           => $docRefId,
            'meta'         => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentReference'],
            ],
            'status'       => 'current',
            'docStatus'    => 'final',
            'type'         => [
                'coding' => [
                    [
                        'system'  => 'https://nrces.in/ndhm/fhir/r4/CodeSystem/ndhm-billing-codes',
                        'code'    => $billingCode['code'],
                        'display' => $billingCode['display'],
                    ],
                    [
                        'system'  => 'http://loinc.org',
                        'code'    => '55108-5',
                        'display' => 'Clinical presentation Document',
                    ],
                ],
                'text' => $title,
            ],
            'subject'      => ['reference' => 'Patient/' . $context['patient']],
            'context'      => [
                'encounter' => [['reference' => 'Encounter/' . $context['encounter']]],
                'related'   => array_map(fn ($inv) => ['reference' => 'Invoice/' . $inv['id']], $invoiceResources),
            ],
            'date'         => $now,
            'description'  => $title,
            'content'      => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language'    => 'en-IN',
                    'data'        => base64_encode($pdfBytes),
                    'url'         => 'Binary/' . $binaryId,
                    'title'       => $title . '.pdf',
                    'creation'    => $now,
                    'size'        => strlen($pdfBytes),
                ],
            ]],
        ];

        $binary = [
            'resourceType' => 'Binary',
            'id'           => $binaryId,
            'meta'         => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Binary'],
            ],
            'contentType'  => 'application/pdf',
            'data'         => base64_encode($pdfBytes),
        ];

        return [
            'docRef' => $docRef,
            'binary' => $binary,
        ];
    }

    /**
     * Generates a consolidated bill PDF (in bytes) for OPD, IPD, or DCD.
     */
    private function generateInvoicePdf(string $module, int $id): ?string
    {
        try {
            $renderer = app(LegacyReportRenderer::class);
            if ($module === 'IPD') {
                $d = app(IpdReportData::class)->dischargeDetailedBill($id);
                if (!$d) {
                    return null;
                }
                return $renderer->standardView('pdf.ipd-discharge-detailed-bill', compact('d'));
            }

            if ($module === 'DCD') {
                $dcd = Dcd::with(['patient', 'doctor', 'bed.wardCategory', 'discharge'])->find($id);
                if (!$dcd) {
                    return null;
                }
                $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
                    ->where('module', 'DCD')
                    ->where('module_id', $id)
                    ->orderBy('charge_date_time')
                    ->get();
                return $renderer->standardView('pdf.dcd-discharge-detailed-bill', compact('dcd', 'charges'));
            }

            if ($module === 'OPD') {
                $opd = Opd::with(['patient'])->find($id);
                if (!$opd) {
                    return null;
                }
                $charges = BillingCharge::with(['details.charge', 'payments', 'chargeDepartment'])
                    ->where('module', 'OPD')
                    ->where('module_id', $id)
                    ->orderBy('charge_date_time')
                    ->get();
                return $renderer->standardView('pdf.consolidated-bill', compact('opd', 'charges'));
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceFhirBuilder: Failed to render invoice PDF', [
                'module' => $module,
                'id'     => $id,
                'error'  => $e->getMessage(),
            ]);
        }

        return null;
    }
}
