<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Spec parity gate (#317 Phase 2).
 *
 * Closes the coverage gap the 2026-07-16 audit found: `composer check` compares
 * no enum members between artifacts and never checks that a `required` entry
 * names a real property. That is exactly how #264 (tools.json advertised a
 * retired enum) and the DunningNotice ghost-required both shipped green.
 *
 * Three checks, all deterministic and hermetic (no network, no DB):
 *   1. required sanity  — every name in a schema's `required` exists in its
 *      `properties`.
 *   2. tools.json ↔ OpenAPI enum parity — each MCP tool input enum matches the
 *      enum of the same-named parameter on the OpenAPI operation it points at.
 *   3. PHP ↔ OpenAPI enum parity — each OpenAPI named-enum schema matches the
 *      value set of its backing PHP enum (by name, with an explicit alias map
 *      for the few whose names legitimately differ).
 */

$root = dirname(__DIR__);
$specPath = $root . '/docs/openapi/openapi.yaml';
$toolsPath = $root . '/docs/mcp/tools.json';

$spec = Yaml::parseFile($specPath);
if (!is_array($spec)) {
    fwrite(STDERR, "openapi.yaml did not parse to a mapping.\n");
    exit(1);
}

$schemas = $spec['components']['schemas'] ?? [];
$paths = $spec['paths'] ?? [];
$errors = [];

/** Resolve a local `$ref` (or an inline schema) to its `enum` array, or null. */
$resolveEnum = static function (array $schema) use (&$schemas): ?array {
    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
        $name = substr($schema['$ref'], strlen('#/components/schemas/'));
        $schema = $schemas[$name] ?? [];
    }

    return isset($schema['enum']) && is_array($schema['enum']) ? $schema['enum'] : null;
};

// --- Check 1: required sanity -------------------------------------------------
foreach ($schemas as $name => $schema) {
    if (!is_array($schema)) {
        continue;
    }
    $props = array_keys($schema['properties'] ?? []);
    foreach ($schema['required'] ?? [] as $req) {
        if ($props !== [] && !in_array($req, $props, true)) {
            $errors[] = sprintf(
                'required sanity: schema `%s` requires `%s`, which is not a declared property.',
                $name,
                $req,
            );
        }
    }
}

// --- Check 2: tools.json ↔ OpenAPI enum parity --------------------------------
// Build operationId -> parameter-name -> enum, from the OpenAPI operations.
$opParamEnums = [];
foreach ($paths as $ops) {
    if (!is_array($ops)) {
        continue;
    }
    foreach ($ops as $op) {
        if (!is_array($op) || !isset($op['operationId'])) {
            continue;
        }
        foreach ($op['parameters'] ?? [] as $param) {
            if (!is_array($param) || !isset($param['name'], $param['schema'])) {
                continue;
            }
            $enum = $resolveEnum($param['schema']);
            if ($enum !== null) {
                $opParamEnums[$op['operationId']][$param['name']] = $enum;
            }
        }
    }
}

$tools = json_decode((string) file_get_contents($toolsPath), true);
foreach ($tools['tools'] ?? [] as $tool) {
    $opId = $tool['source']['operationId'] ?? null;
    $props = $tool['inputSchema']['properties'] ?? [];
    if (!is_string($opId) || !is_array($props)) {
        continue;
    }
    foreach ($props as $field => $schema) {
        if (!is_array($schema) || !isset($schema['enum']) || !is_array($schema['enum'])) {
            continue;
        }
        $specEnum = $opParamEnums[$opId][$field] ?? null;
        if ($specEnum === null) {
            $errors[] = sprintf(
                'tools.json parity: tool `%s` declares an enum for `%s`, but OpenAPI operation `%s` has no enum parameter of that name.',
                $tool['name'] ?? $opId,
                $field,
                $opId,
            );
            continue;
        }
        if (array_values($schema['enum']) !== array_values($specEnum)) {
            $errors[] = sprintf(
                'tools.json parity: tool `%s` field `%s` enum [%s] != OpenAPI `%s` [%s].',
                $tool['name'] ?? $opId,
                $field,
                implode(', ', $schema['enum']),
                $opId,
                implode(', ', $specEnum),
            );
        }
    }
}

// --- Check 3: PHP ↔ OpenAPI named-enum parity ---------------------------------
// OpenAPI enum schema name -> PHP enum FQCN. Names match except where the API
// vocabulary and the backend class name legitimately differ (recorded here).
$phpEnumFor = [
    'UserRole' => 'NeneClear\\Auth\\Role',
    'UserStatus' => 'NeneClear\\User\\UserStatus',
    'AccountType' => 'NeneClear\\BankImport\\AccountType',
    'BankImportBatchStatus' => 'NeneClear\\BankImport\\BankImportBatchStatus',
    'BankTransactionStatus' => 'NeneClear\\BankImport\\BankTransactionStatus',
    'ReconciliationStatus' => 'NeneClear\\Reconciliation\\ReconciliationStatus',
    'ClientCreditStatus' => 'NeneClear\\Reconciliation\\ClientCreditStatus',
    'ManualReceivableStatus' => 'NeneClear\\Receivable\\ManualReceivableStatus',
    // UpstreamInvoiceStatus is owned by NeNe Invoice (no local backing enum).
];

foreach ($schemas as $name => $schema) {
    if (!is_array($schema) || !isset($schema['enum']) || !is_array($schema['enum'])) {
        continue;
    }
    if (!isset($phpEnumFor[$name])) {
        continue; // e.g. UpstreamInvoiceStatus — externally owned; skip.
    }
    $fqcn = $phpEnumFor[$name];
    if (!enum_exists($fqcn)) {
        $errors[] = sprintf('PHP parity: OpenAPI `%s` maps to `%s`, which is not an enum.', $name, $fqcn);
        continue;
    }
    $phpValues = array_map(static fn ($case) => $case->value, $fqcn::cases());
    $specValues = array_values($schema['enum']);
    sort($phpValues);
    $sortedSpec = $specValues;
    sort($sortedSpec);
    if ($phpValues !== $sortedSpec) {
        $errors[] = sprintf(
            'PHP parity: OpenAPI `%s` [%s] != PHP `%s` [%s].',
            $name,
            implode(', ', $specValues),
            $fqcn,
            implode(', ', array_map(static fn ($c) => $c->value, $fqcn::cases())),
        );
    }
}

// --- Report -------------------------------------------------------------------
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "Spec parity error: {$error}\n");
    }
    exit(1);
}

echo "Spec parity OK — required lists sound; enums agree across OpenAPI, MCP tools, and PHP.\n";
