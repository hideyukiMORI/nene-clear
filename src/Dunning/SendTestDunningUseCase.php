<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

/**
 * Sends a one-off *test* dunning email to an operator-supplied address so they
 * can see the real rendered message in their own inbox before dunning a client.
 *
 * Unlike a real send it does **not** record a {@see DunningNotice} (and writes
 * no audit event), it goes to the explicit `to` the operator typed (never the
 * client unless they enter it), and the subject is marked with a test prefix.
 * It reuses {@see DunningMessageRenderer} so the test is byte-identical to the
 * real message (#194).
 */
final readonly class SendTestDunningUseCase
{
    public function __construct(
        private InvoiceUpstreamClientInterface $invoiceClient,
        private DunningMessageRenderer $renderer,
        private DunningMailerInterface $mailer,
    ) {
    }

    public function execute(SendTestDunningInput $input): string
    {
        $to = trim($input->to);
        if ($to === '' || !str_contains($to, '@')) {
            throw new ValidationException([
                new ValidationError('to', 'A valid test recipient email is required.', 'invalid'),
            ]);
        }

        $invoice = $this->invoiceClient->getInvoice($input->organizationId, $input->invoiceId);
        $client = $this->invoiceClient->getClient($input->organizationId, $invoice->clientId);

        $this->mailer->send(new DunningMailPayload(
            to: $to,
            subject: $this->renderer->testSubject($input->stage, $invoice->invoiceNumber),
            body: $this->renderer->body($input->stage, $client->contactName, $invoice->invoiceNumber, $invoice->dueAt, $invoice->outstandingCents),
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            dunningNoticeId: 0,
        ));

        return $to;
    }
}
