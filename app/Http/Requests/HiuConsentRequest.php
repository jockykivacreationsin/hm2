<?php

namespace App\Http\Requests;

use App\Services\Abdm\HiuConsentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * "Request Records From Abha" form (HIU consent request): purpose, HI types, data date range and
 * consent expiry. Dates arrive as d-m-Y (flatpickr) or Y-m-d; the expiry as d-m-Y H:i / h:i A.
 * Error messages are the ones the panels have always shown (legacy hiu_ajax.php).
 */
class HiuConsentRequest extends AbdmJsonRequest
{
    public const PURPOSES = ['CAREMGT', 'BTG', 'PUBHLTH', 'HPAYMT', 'DSRCH', 'PATRQT'];

    /** @var array{from: ?string, to: ?string, expiry: ?string, error: ?array}|null */
    private ?array $window = null;

    protected function prepareForValidation(): void
    {
        $types = $this->input('hi_types') ?? $this->input('hi_types_value') ?? [];

        $this->merge([
            'hi_types'     => array_values(array_intersect(array_filter((array) $types, 'is_string'), HiuConsentService::HI_TYPES)),
            'purpose_code' => trim((string) $this->input('purpose_code', 'CAREMGT')),
        ]);
    }

    public function rules(): array
    {
        return [
            'hi_types'     => ['required', 'array', 'min:1'],
            'purpose_code' => ['required', Rule::in(self::PURPOSES)],
            'from_date'    => ['nullable', 'string'],
            'to_date'      => ['nullable', 'string'],
            'expiry'       => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'hi_types.required'     => 'Select at least one Health Info type',
            'hi_types.min'          => 'Select at least one Health Info type',
            'purpose_code.required' => 'Invalid purpose of request',
            'purpose_code.in'       => 'Invalid purpose of request',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $error = $this->window()['error'];
            if ($validator->errors()->isEmpty() && $error) {
                $validator->errors()->add($error[0], $error[1]);
            }
        });
    }

    /** Payload for HiuConsentService::initiateRequest() (ISO-8601 UTC timestamps). */
    public function consentPayload(string $abhaAddress): array
    {
        $window = $this->window();

        return [
            'abha_address' => $abhaAddress,
            'hi_types'     => $this->input('hi_types'),
            'purpose_code' => $this->input('purpose_code'),
            'date_from'    => $window['from'],
            'date_to'      => $window['to'],
            'expiry_at'    => $window['expiry'],
        ];
    }

    /** Parses the date range and expiry once; `error` is [field, message] for the first problem. */
    private function window(): array
    {
        if ($this->window !== null) {
            return $this->window;
        }

        $window = ['from' => null, 'to' => null, 'expiry' => null, 'error' => null];
        $fail = function (string $field, string $message) use (&$window) {
            $window['error'] ??= [$field, $message];
        };

        $from   = trim((string) $this->input('from_date', ''));
        $to     = trim((string) $this->input('to_date', ''));
        $expiry = trim((string) $this->input('expiry', ''));

        if ($from !== '') {
            $ts = $this->dateTimestamp($from);
            if ($ts === false) {
                $fail('from_date', 'Invalid Health Info "From" date');
            } else {
                $window['from'] = gmdate('Y-m-d\T00:00:00.000\Z', $ts);
            }
        }
        if ($to !== '') {
            $ts = $this->dateTimestamp($to);
            if ($ts === false) {
                $fail('to_date', 'Invalid Health Info "To" date');
            } else {
                $window['to'] = gmdate('Y-m-d\T23:59:59.000\Z', $ts);
            }
        }
        if ($window['from'] !== null && $window['to'] !== null && $window['from'] > $window['to']) {
            $fail('from_date', 'The Health Info "From" date must not be after the "To" date');
        }

        if ($expiry !== '') {
            $ts = $this->timestamp($expiry, ['d-m-Y H:i', 'd-m-Y h:i A', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d']);
            if ($ts === false) {
                $fail('expiry', 'Invalid Consent Expiry date/time');
            } elseif ($ts <= time()) {
                $fail('expiry', 'Consent Expiry must be a date/time in the future');
            } else {
                $window['expiry'] = gmdate('Y-m-d\TH:i:s.000\Z', $ts);
            }
        }

        return $this->window = $window;
    }

    /**
     * A calendar date as typed (d-m-Y or Y-m-d), as midnight UTC: the Z-suffixed range bounds keep the
     * chosen day whatever the app timezone is. Anything else falls back to strtotime().
     */
    private function dateTimestamp(string $value): int|false
    {
        foreach (['!d-m-Y', '!Y-m-d'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value, new \DateTimeZone('UTC'));
            if ($dt) {
                return $dt->getTimestamp();
            }
        }

        return strtotime($value);
    }

    /** First matching format wins; anything else falls back to strtotime(). */
    private function timestamp(string $value, array $formats): int|false
    {
        foreach ($formats as $format) {
            if ($dt = \DateTime::createFromFormat($format, $value)) {
                return $dt->getTimestamp();
            }
        }

        return strtotime($value);
    }
}
