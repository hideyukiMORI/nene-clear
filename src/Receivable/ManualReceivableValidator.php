<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Shared request-body validation for create/update. Returns a normalized array
 * of fields, or throws {@see ValidationException} (→ 422). Validating here keeps
 * the use cases free of HTTP/body concerns. Currency is JPY-only for now and
 * dates are calendar `YYYY-MM-DD`.
 *
 * @phpstan-type ManualReceivableFields array{
 *   reference_number: string, client_name: string, recipient_email: ?string,
 *   total_cents: int, currency: string, issued_at: ?string, due_at: ?string
 * }
 */
final readonly class ManualReceivableValidator
{
    /**
     * @param array<string, mixed> $body
     *
     * @return ManualReceivableFields
     */
    public static function validate(array $body): array
    {
        $errors = [];

        $referenceNumber = trim((string) ($body['reference_number'] ?? ''));
        if ($referenceNumber === '') {
            $errors[] = new ValidationError('reference_number', 'Reference number is required.', 'required');
        } elseif (mb_strlen($referenceNumber) > 64) {
            $errors[] = new ValidationError('reference_number', 'Reference number is too long (max 64).', 'too_long');
        }

        $clientName = trim((string) ($body['client_name'] ?? ''));
        if ($clientName === '') {
            $errors[] = new ValidationError('client_name', 'Client name is required.', 'required');
        } elseif (mb_strlen($clientName) > 255) {
            $errors[] = new ValidationError('client_name', 'Client name is too long (max 255).', 'too_long');
        }

        $rawTotal = $body['total_cents'] ?? null;
        $totalCents = is_int($rawTotal) || (is_string($rawTotal) && ctype_digit($rawTotal)) ? (int) $rawTotal : null;
        if ($totalCents === null || $totalCents <= 0) {
            $errors[] = new ValidationError('total_cents', 'A positive total amount (in cents) is required.', 'invalid');
        }

        $recipientEmail = self::optionalString($body, 'recipient_email');
        if ($recipientEmail !== null && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = new ValidationError('recipient_email', 'Recipient email is not a valid address.', 'invalid');
        }

        $currencyRaw = self::optionalString($body, 'currency');
        $currency = $currencyRaw ?? 'JPY';
        if ($currency !== 'JPY') {
            $errors[] = new ValidationError('currency', 'Only JPY is supported.', 'unsupported');
        }

        $issuedAt = self::optionalString($body, 'issued_at');
        if ($issuedAt !== null && !self::isCalendarDate($issuedAt)) {
            $errors[] = new ValidationError('issued_at', 'Issue date must be YYYY-MM-DD.', 'invalid');
        }

        $dueAt = self::optionalString($body, 'due_at');
        if ($dueAt !== null && !self::isCalendarDate($dueAt)) {
            $errors[] = new ValidationError('due_at', 'Due date must be YYYY-MM-DD.', 'invalid');
        }

        if ($errors !== [] || $totalCents === null) {
            throw new ValidationException($errors);
        }

        return [
            'reference_number' => $referenceNumber,
            'client_name' => $clientName,
            'recipient_email' => $recipientEmail,
            'total_cents' => $totalCents,
            'currency' => $currency,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function optionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function isCalendarDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
