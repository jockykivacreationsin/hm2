<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** The Composition sections and narrative paragraphs of a discharge summary while its sections are collected. */
final class SummaryDraft
{
    /** @var list<array<string, mixed>> */
    private array $sections = [];

    /** @var list<string> */
    private array $narrative = [];

    /**
     * A section with a SNOMED code that points at its entries.
     *
     * @param list<array<string, string>> $entries
     */
    public function section(string $title, string $snomedCode, string $snomedDisplay, array $entries): void
    {
        $this->sections[] = ['title' => $title, 'code' => FhirCoding::snomed($snomedCode, $snomedDisplay), 'entry' => $entries];
    }

    /**
     * Adds entries to an already collected section (matched by title).
     *
     * @param list<array<string, string>> $entries
     * @return bool false when no section with that title exists yet
     */
    public function appendToSection(string $title, array $entries): bool
    {
        foreach ($this->sections as $i => $section) {
            if (($section['title'] ?? null) === $title && isset($section['entry'])) {
                $this->sections[$i]['entry'] = array_merge($section['entry'], $entries);

                return true;
            }
        }

        return false;
    }

    /** A section of any other shape. */
    public function addSection(array $section): void
    {
        $this->sections[] = $section;
    }

    public function narrate(string $label, string $text): void
    {
        $this->narrative[] = '<p><b>' . $label . ':</b> ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /** @return list<array<string, mixed>> */
    public function sections(): array
    {
        return $this->sections;
    }

    public function narrativeHtml(): string
    {
        $parts = $this->narrative ?: ['<p>IPD admission discharge summary.</p>'];

        return implode('', $parts);
    }
}
