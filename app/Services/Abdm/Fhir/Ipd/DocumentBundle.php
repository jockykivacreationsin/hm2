<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** The shared "document" Bundle wrapper of the IPD HI types, and the optional urn:uuid rewrite. */
final class DocumentBundle
{
    /**
     * NRCeS's DocumentBundle profile requires Bundle.meta and Bundle.identifier, and the standalone IPD
     * DiagnosticReport path is especially sensitive to that wrapper metadata in the ABHA app.
     *
     * @param list<array{fullUrl:string, resource:array}> $entries
     */
    public function wrap(int $ipdId, string $identifierSuffix, string $timestamp, array $entries): array
    {
        return [
            'resourceType' => 'Bundle',
            'id' => abdm_generate_uuid(),
            'meta' => [
                'versionId' => '1',
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle'],
                'lastUpdated' => $timestamp,
            ],
            'identifier' => [
                'system' => 'https://ndhm.in',
                'value' => 'IPD-' . $identifierSuffix . '-' . $ipdId,
            ],
            'type' => 'document',
            'timestamp' => $timestamp,
            'entry' => $entries,
        ];
    }

    /**
     * Rewrites the local `ResourceType/<id>` fullUrls and the matching internal Reference.reference strings to
     * `urn:uuid:<id>`, as in the published NRCeS document-bundle examples. Kept as an explicit step so only the
     * builders that need it are affected.
     *
     * @param list<array{fullUrl:string, resource:array}> $entries
     * @return list<array{fullUrl:string, resource:array}>
     */
    public function rewriteToUrnUuid(array $entries): array
    {
        $map = $this->urnMap($entries);

        foreach ($entries as &$entry) {
            $resource = $entry['resource'] ?? null;
            if (is_array($resource) && !empty($resource['id'])) {
                $entry['fullUrl'] = 'urn:uuid:' . $resource['id'];
            }
            $entry = $this->rewriteReferences($entry, $map);
        }
        unset($entry);

        return $entries;
    }

    /** @return array<string, string> old local url => urn:uuid */
    private function urnMap(array $entries): array
    {
        $map = [];

        foreach ($entries as $entry) {
            $resource = $entry['resource'] ?? null;
            if (!is_array($resource) || empty($resource['resourceType']) || empty($resource['id'])) {
                continue;
            }
            $urn = 'urn:uuid:' . $resource['id'];
            if (!empty($entry['fullUrl']) && is_string($entry['fullUrl'])) {
                $map[$entry['fullUrl']] = $urn;
            }
            $map[$resource['resourceType'] . '/' . $resource['id']] = $urn;
        }

        return $map;
    }

    /** @param array<string, string> $map */
    private function rewriteReferences(mixed $value, array $map): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            if ($key === 'reference' && is_string($child) && isset($map[$child])) {
                $value[$key] = $map[$child];
            } else {
                $value[$key] = $this->rewriteReferences($child, $map);
            }
        }

        return $value;
    }
}
