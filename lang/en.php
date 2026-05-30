<?php

declare(strict_types=1);

/**
 * English message catalog — single source of truth for all English user-facing strings.
 * Keys: 'problem.{slug}.title', 'problem.{slug}.detail', 'dunning_email.*'
 * Slugs must be registered in docs/explanation/terminology.md §4 before use.
 *
 * @return array<string, string>
 */
return [

    // -------------------------------------------------------------------------
    // Problem Details — titles and details (API error responses)
    // -------------------------------------------------------------------------

    'problem.validation-failed.title'  => 'Validation Failed',
    'problem.validation-failed.detail' => 'One or more input fields are invalid.',
    'problem.validation-failed.csv-prefix' => 'The uploaded CSV could not be parsed: ',

    'problem.unauthorized.title'  => 'Unauthorized',
    'problem.unauthorized.detail' => 'The authenticated user no longer exists.',

    'problem.invalid-credentials.title'  => 'Invalid Credentials',
    'problem.invalid-credentials.detail' => 'Email or password is incorrect.',

    'problem.insufficient-capability.title'  => 'Insufficient Capability',
    'problem.insufficient-capability.detail' => 'Your role lacks the capability required for this action.',

    'problem.organization-not-found.title'  => 'Organization Not Found',
    'problem.organization-not-found.detail' => 'The requested organization was not found.',

    'problem.organization-already-exists.title'  => 'Organization Already Exists',
    'problem.organization-already-exists.detail' => 'An organization with that slug already exists.',

    'problem.user-not-found.title'  => 'User Not Found',
    'problem.user-not-found.detail' => 'The requested user was not found.',

    'problem.user-already-exists.title'  => 'User Already Exists',
    'problem.user-already-exists.detail' => 'A user with that email already exists.',

    'problem.bank-account-not-found.title'  => 'Bank Account Not Found',
    'problem.bank-account-not-found.detail' => 'The requested bank account was not found.',

    'problem.bank-import-batch-not-found.title'  => 'Bank Import Batch Not Found',
    'problem.bank-import-batch-not-found.detail' => 'The requested bank import batch was not found.',

    'problem.bank-transaction-not-found.title'  => 'Bank Transaction Not Found',
    'problem.bank-transaction-not-found.detail' => 'The requested bank transaction was not found.',

    'problem.reconciliation-not-found.title'  => 'Reconciliation Not Found',
    'problem.reconciliation-not-found.detail' => 'The requested reconciliation was not found.',

    'problem.client-credit-not-found.title'  => 'Client Credit Not Found',
    'problem.client-credit-not-found.detail' => 'The requested client credit was not found.',

    'problem.dunning-notice-not-found.title'  => 'Dunning Notice Not Found',
    'problem.dunning-notice-not-found.detail' => 'The requested dunning notice was not found.',

    // invalid-state-transition: shared title, context-specific details
    'problem.invalid-state-transition.title'                          => 'Invalid State Transition',
    'problem.invalid-state-transition.batch-reversed.detail'          => 'The batch is already reversed.',
    'problem.invalid-state-transition.batch-has-matched.detail'       => 'One or more transactions are already matched; unmatch them before reversing the batch.',
    'problem.invalid-state-transition.tx-not-matchable.detail'        => 'The bank transaction cannot be matched in its current status.',
    'problem.invalid-state-transition.reconciliation-reversed.detail' => 'This reconciliation is already reversed.',

    'problem.duplicate-bank-import.title'  => 'Duplicate Bank Import',
    'problem.duplicate-bank-import.detail' => 'A batch with this file hash was already imported.',

    'problem.allocation-exceeds-outstanding.title'  => 'Allocation Exceeds Outstanding',
    'problem.allocation-exceeds-outstanding.detail' => 'The allocation amount exceeds the invoice outstanding balance.',

    'problem.upstream-invoice-unavailable.title'  => 'Invoice Upstream Unavailable',
    'problem.upstream-invoice-unavailable.detail' => 'The Invoice API is unreachable; match confirmation is blocked.',

    // Entries for upstream-invoice-not-found and upstream-client-not-found are
    // used by UpstreamInvoiceNotFoundExceptionHandler and
    // UpstreamClientNotFoundExceptionHandler (PR #62, pending merge).
    'problem.upstream-invoice-not-found.title'  => 'Invoice Not Found in Upstream',
    'problem.upstream-invoice-not-found.detail' => 'The referenced invoice or client does not exist in the Invoice system.',

    'problem.upstream-client-not-found.title'  => 'Client Not Found in Upstream',
    'problem.upstream-client-not-found.detail' => 'The referenced client does not exist (or has been deleted) in the Invoice system.',

    'problem.invoice-not-eligible-for-dunning.title'  => 'Invoice Not Eligible for Dunning',
    'problem.invoice-not-eligible-for-dunning.detail' => 'The invoice is already paid, voided, or has no outstanding balance.',

    'problem.dunning-too-frequent.title'  => 'Dunning Too Frequent',
    'problem.dunning-too-frequent.detail' => 'The minimum interval between dunning notices has not elapsed.',

    'problem.credit-exceeds-remaining.title'  => 'Credit Exceeds Remaining Balance',
    'problem.credit-exceeds-remaining.detail' => 'The requested amount exceeds the remaining credit balance.',

    // -------------------------------------------------------------------------
    // Dunning email — sent to clients (ADR 0005: Japanese clients; ja.php is
    // the operative template; this entry is provided for completeness only)
    // sprintf args: (invoice_number)
    // -------------------------------------------------------------------------
    'dunning_email.subject' => '[Reminder] Invoice %s is overdue',

    // sprintf args: (contact_name, invoice_number, due_at, outstanding, due_at, outstanding)
    'dunning_email.body' => "Dear %s,\n\n"
        . "This is a reminder that invoice %s (due: %s) has an outstanding balance of \xC2\xA5%s (as of %s).\n\n"
        . "Outstanding balance: \xC2\xA5%s\n\n"
        . "Please arrange payment at your earliest convenience.\n\n"
        . 'If you have already made payment, please disregard this notice.',

];
