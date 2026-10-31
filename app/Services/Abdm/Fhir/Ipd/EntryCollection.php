<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** The Bundle.entry list of one bundle under construction. */
final class EntryCollection
{
    /** @var list<array{fullUrl:string, resource:array}> */
    private array $entries = [];

    /**
     * Appends a resource with fullUrl `<ResourceType>/<id>` and returns the matching
     * `['reference' => '<ResourceType>/<id>']` so callers can link to it from a section.
     *
     * @return array{reference:string}
     */
    public function add(array $resource): array
    {
        $url = $resource['resourceType'] . '/' . $resource['id'];
        $this->entries[] = ['fullUrl' => $url, 'resource' => $resource];

        return ['reference' => $url];
    }

    public function prepend(array $resource): void
    {
        array_unshift($this->entries, [
            'fullUrl' => $resource['resourceType'] . '/' . $resource['id'],
            'resource' => $resource,
        ]);
    }

    /** @return list<array{fullUrl:string, resource:array}> */
    public function all(): array
    {
        return $this->entries;
    }
}
