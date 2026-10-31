<?php

namespace App\Support;

/**
 * ABHA-creation Terms & Conditions, stored in settings.abha_creation_terms as JSON
 * {"items":[{"id":1,"text":"..."}],"title":"Terms and Conditions"} (the format the legacy pages read).
 *
 * parse()  reads whatever is stored: the JSON, JSON that a rich-text editor wrapped in <p> and entity-encoded (an earlier
 *          version of the Settings page did that, which made the terms unreadable), or plain text (one term per line).
 * toJson() builds the clean JSON that is stored: tags stripped, empty terms dropped, ids renumbered 1..n.
 */
final class AbhaTerms
{
    public const DEFAULT_TITLE = 'Terms and Conditions';

    /** @return array{title: string, items: array<int, array{id: int, text: string}>} */
    public static function parse(mixed $raw): array
    {
        $data = self::decode($raw);

        $title = is_array($data) && isset($data['title']) && is_string($data['title']) ? self::clean($data['title']) : '';
        $items = [];

        if (is_array($data)) {
            foreach ((array) ($data['items'] ?? []) as $item) {
                $text = self::clean(is_array($item) ? ($item['text'] ?? '') : $item);
                if ($text !== '') {
                    $items[] = $text;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            foreach (preg_split('/\R+/', self::clean($raw, keepLines: true)) ?: [] as $line) {
                if (trim($line) !== '') {
                    $items[] = trim($line);
                }
            }
        }

        return [
            'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
            'items' => array_map(fn (string $text, int $i) => ['id' => $i + 1, 'text' => $text], $items, array_keys($items)),
        ];
    }

    /** The JSON to store for what was typed in the Settings form (or any stored value): always clean and valid. */
    public static function toJson(mixed $raw): string
    {
        $terms = self::parse($raw);

        return json_encode(
            ['items' => $terms['items'], 'title' => $terms['title']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /** @return mixed the decoded array, or null when the value is not JSON of the terms shape */
    private static function decode(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // JSON that a rich-text editor wrapped in <p> and entity-encoded
            $data = json_decode(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        }

        return is_array($data) ? $data : null;
    }

    private static function clean(mixed $value, bool $keepLines = false): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $keepLines ? trim($text) : trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
