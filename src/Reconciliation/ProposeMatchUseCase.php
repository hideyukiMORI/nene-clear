<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\ClockInterface;
use NeneClear\BankImport\BankTransactionNotFoundException;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ProposeMatchUseCase implements ProposeMatchUseCaseInterface
{
    private const int MAX_SUGGESTIONS = 10;
    private const int DUE_SOON_DAYS = 30;

    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ProposeMatchInput $input): ProposeMatchOutput
    {
        $tx = $this->transactions->findById($input->organizationId, $input->bankTransactionId);

        if ($tx === null) {
            throw new BankTransactionNotFoundException($input->bankTransactionId);
        }

        $invoices = $this->invoiceClient->listInvoices($input->organizationId, ['issued', 'partially_paid']);

        $today = $this->clock->now();
        $suggestions = [];

        foreach ($invoices as $invoice) {
            $score = 0.0;
            $reasons = [];

            if ($invoice->outstandingCents === $tx->amountCents) {
                $score += 0.5;
                $reasons[] = 'exact amount match';
            }

            if (stripos($tx->counterpartyText, $invoice->invoiceNumber) !== false) {
                $score += 0.3;
                $reasons[] = 'invoice number in counterparty';
            }

            if ($score > 0.0 || count($reasons) > 0) {
                $dueAt = new \DateTimeImmutable($invoice->dueAt);
                $daysUntilDue = (int) $today->diff($dueAt)->days;
                if ($dueAt >= $today && $daysUntilDue <= self::DUE_SOON_DAYS) {
                    $score += 0.2;
                    $reasons[] = 'due soon';
                }
            }

            if ($score > 0.0) {
                $suggestions[] = new MatchSuggestion(
                    invoiceId: $invoice->invoiceId,
                    invoiceNumber: $invoice->invoiceNumber,
                    amountCents: $invoice->totalCents,
                    outstandingCents: $invoice->outstandingCents,
                    score: $score,
                    reason: implode('; ', $reasons),
                );
            }
        }

        usort($suggestions, static fn (MatchSuggestion $a, MatchSuggestion $b): int => $b->score <=> $a->score);

        return new ProposeMatchOutput(
            bankTransactionId: $input->bankTransactionId,
            suggestions: array_slice($suggestions, 0, self::MAX_SUGGESTIONS),
        );
    }
}
