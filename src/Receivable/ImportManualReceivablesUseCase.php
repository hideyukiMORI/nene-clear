<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;

/**
 * Bulk-imports receivables from a CSV by reusing the single-create path: each
 * row is validated ({@see ManualReceivableValidator}) and created
 * ({@see CreateManualReceivableUseCaseInterface}, which audits and dedupes). A
 * duplicate `reference_number` is skipped (not an error); an invalid row is
 * reported by its CSV line number. A header missing a required column fails the
 * whole request (422) since every row would otherwise be invalid.
 */
final readonly class ImportManualReceivablesUseCase implements ImportManualReceivablesUseCaseInterface
{
    private const array REQUIRED_HEADERS = ['reference_number', 'client_name', 'total_cents'];

    public function __construct(
        private ManualReceivableCsvParser $parser,
        private CreateManualReceivableUseCaseInterface $create,
    ) {
    }

    public function execute(ImportManualReceivablesInput $input): ImportManualReceivablesOutput
    {
        $parsed = $this->parser->parse($input->contents);

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $parsed->headers));
        if ($missing !== []) {
            throw new ValidationException(array_map(
                static fn (string $column): ValidationError => new ValidationError(
                    $column,
                    sprintf('A "%s" column is required in the CSV header.', $column),
                    'required',
                ),
                $missing,
            ));
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($parsed->rows as $row) {
            try {
                $fields = ManualReceivableValidator::validate($row['data']);
                $this->create->execute(new CreateManualReceivableInput(
                    organizationId: $input->organizationId,
                    referenceNumber: $fields['reference_number'],
                    clientName: $fields['client_name'],
                    recipientEmail: $fields['recipient_email'],
                    totalCents: $fields['total_cents'],
                    currency: $fields['currency'],
                    issuedAt: $fields['issued_at'],
                    dueAt: $fields['due_at'],
                    actorUserId: $input->actorUserId,
                ));
                ++$created;
            } catch (ManualReceivableAlreadyExistsException) {
                ++$skipped;
            } catch (ValidationException $e) {
                $errors[] = [
                    'row' => $row['row'],
                    'errors' => array_map(
                        static fn (ValidationError $error): array => ['field' => $error->field, 'message' => $error->message],
                        $e->errors(),
                    ),
                ];
            }
        }

        return new ImportManualReceivablesOutput($created, $skipped, $errors);
    }
}
