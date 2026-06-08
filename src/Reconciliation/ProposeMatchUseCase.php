<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\ClockInterface;
use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionNotFoundException;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\InvoiceUpstream\UpstreamInvoiceUnavailableException;
use NeneClear\Receivable\ManualReceivableFilter;
use NeneClear\Receivable\ManualReceivableRepositoryInterface;
use NeneClear\Receivable\ManualReceivableStatus;

final readonly class ProposeMatchUseCase implements ProposeMatchUseCaseInterface
{
    private const int MAX_SUGGESTIONS = 10;
    private const int DUE_SOON_DAYS = 30;
    /** Upper bound on manual receivables scanned for candidates. */
    private const int MANUAL_SCAN_LIMIT = 200;

    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private ManualReceivableRepositoryInterface $manualReceivables,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ProposeMatchInput $input): ProposeMatchOutput
    {
        $tx = $this->transactions->findById($input->organizationId, $input->bankTransactionId);

        if ($tx === null) {
            throw new BankTransactionNotFoundException($input->bankTransactionId);
        }

        $today = $this->clock->now();

        // Degraded mode (ADR 0014): if the Invoice upstream is unavailable, still
        // return the manual candidates and flag the gap, rather than failing the
        // whole proposal. A user with no upstream configured hits the fake client
        // (empty list, no throw), so this only affects a configured-but-down Invoice.
        $upstreamUnavailable = false;
        try {
            $upstream = $this->upstreamSuggestions($input->organizationId, $tx, $today);
        } catch (UpstreamInvoiceUnavailableException) {
            $upstream = [];
            $upstreamUnavailable = true;
        }

        $suggestions = [
            ...$upstream,
            ...$this->manualSuggestions($input->organizationId, $tx, $today),
        ];

        usort($suggestions, static fn (MatchSuggestion $a, MatchSuggestion $b): int => $b->score <=> $a->score);

        return new ProposeMatchOutput(
            bankTransactionId: $input->bankTransactionId,
            suggestions: array_slice($suggestions, 0, self::MAX_SUGGESTIONS),
            upstreamUnavailable: $upstreamUnavailable,
        );
    }

    /**
     * @return list<MatchSuggestion>
     */
    private function upstreamSuggestions(int $organizationId, BankTransaction $tx, \DateTimeImmutable $today): array
    {
        $invoices = $this->invoiceClient->listInvoices($organizationId, ['issued', 'partially_paid']);

        $out = [];
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
            if ($invoice->dueAt !== null) {
                $score += $this->dueSoonBonus($invoice->dueAt, $today, $score > 0.0 || $reasons !== [], $reasons);
            }

            if ($score > 0.0) {
                $out[] = MatchSuggestion::upstream(
                    invoiceId: $invoice->invoiceId,
                    invoiceNumber: $invoice->invoiceNumber,
                    amountCents: $invoice->totalCents,
                    outstandingCents: $invoice->outstandingCents,
                    score: $score,
                    reason: implode('; ', $reasons),
                );
            }
        }

        return $out;
    }

    /**
     * @return list<MatchSuggestion>
     */
    private function manualSuggestions(int $organizationId, BankTransaction $tx, \DateTimeImmutable $today): array
    {
        $receivables = $this->manualReceivables->findByOrganization($organizationId, new ManualReceivableFilter(), self::MANUAL_SCAN_LIMIT, 0);

        $out = [];
        foreach ($receivables as $receivable) {
            if ($receivable->status !== ManualReceivableStatus::Open && $receivable->status !== ManualReceivableStatus::PartiallyPaid) {
                continue;
            }

            $score = 0.0;
            $reasons = [];

            if ($receivable->outstandingCents === $tx->amountCents) {
                $score += 0.5;
                $reasons[] = 'exact amount match';
            }
            if ($receivable->clientName !== '' && stripos($tx->counterpartyText, $receivable->clientName) !== false) {
                $score += 0.3;
                $reasons[] = 'payer name in counterparty';
            }
            if ($receivable->dueAt !== null) {
                $score += $this->dueSoonBonus($receivable->dueAt, $today, $score > 0.0 || $reasons !== [], $reasons);
            }

            if ($score > 0.0 && $receivable->id !== null) {
                $out[] = MatchSuggestion::manual(
                    manualReceivableId: $receivable->id,
                    referenceNumber: $receivable->referenceNumber,
                    amountCents: $receivable->totalCents,
                    outstandingCents: $receivable->outstandingCents,
                    score: $score,
                    reason: implode('; ', $reasons),
                );
            }
        }

        return $out;
    }

    /**
     * +0.2 when the receivable is due within the next {@see DUE_SOON_DAYS} days,
     * but only when there is already another matching signal. Appends the reason.
     *
     * @param list<string> $reasons
     */
    private function dueSoonBonus(string $dueAt, \DateTimeImmutable $today, bool $hasOtherSignal, array &$reasons): float
    {
        if (!$hasOtherSignal) {
            return 0.0;
        }

        $due = new \DateTimeImmutable($dueAt);
        $daysUntilDue = (int) $today->diff($due)->days;
        if ($due >= $today && $daysUntilDue <= self::DUE_SOON_DAYS) {
            $reasons[] = 'due soon';

            return 0.2;
        }

        return 0.0;
    }
}
