<?php

namespace App\Services\Abdm\Fhir\Dcd;

/** Composition sections and the narrative paragraphs collected while the DischargeSummary is built. */
final class SummarySections
{
    /** @var list<array<string, mixed>> */
    private array $sections = [];

    /** @var list<string> */
    private array $narrative = [];

    /** @param array<string, mixed> $section */
    public function add(array $section): void
    {
        $this->sections[] = $section;
    }

    /** Adds a section holding the escaped text; blank values are skipped. */
    public function addText(string $title, string $code, string $display, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $this->sections[] = [
            'title' => $title,
            'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]],
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>'],
        ];
    }

    /** Adds a section that points at already-created resources. */
    public function addEntries(string $title, string $code, string $display, array $entryReferences): void
    {
        $this->sections[] = [
            'title' => $title,
            'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]],
            'entry' => $entryReferences,
        ];
    }

    /**
     * Links already-created resources (the attached PDF) from the section with the given SNOMED code, so the receiving
     * app can reach them from the Composition. The NRCeS DischargeSummaryRecord allows only ONE section per code, so an
     * existing section of that code (e.g. the text-only Discharge Note, 373942005) gets the entries added; a new
     * section is created only when none exists.
     *
     * @param list<array<string, string>> $entryReferences
     */
    public function linkEntries(string $title, string $code, string $display, array $entryReferences): void
    {
        foreach ($this->sections as $i => $section) {
            if (($section['code']['coding'][0]['code'] ?? null) === $code) {
                $this->sections[$i]['entry'] = array_merge($section['entry'] ?? [], $entryReferences);

                return;
            }
        }

        $this->addEntries($title, $code, $display, $entryReferences);
    }

    public function addNarrative(string $html): void
    {
        $this->narrative[] = $html;
    }

    /** @return list<array<string, mixed>> */
    public function sections(): array
    {
        return $this->sections;
    }

    /** Narrative HTML for the Composition, falling back to a generic line when nothing was recorded. */
    public function narrativeHtml(): string
    {
        $narrative = $this->narrative ?: ['<p>DCD Discharge Summary.</p>'];

        return implode('', $narrative);
    }
}
