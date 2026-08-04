<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\Http\ApplicationFactory;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ClearSettingsHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private FakeInvoiceUpstreamClient $invoiceClient;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-settings-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;

        SchemaFixture::createUsers($query);

        SchemaFixture::createTotpTables($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createClearSettings($query);
        SchemaFixture::createAuditEvents($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin));
        $users->save($this->user('viewer@acme.example', Role::Viewer));

        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->app = ApplicationFactory::create(query: $query, transactionManager: $kit->transactionManager, jwtSecret: self::SECRET, invoiceClient: $this->invoiceClient);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function user(string $email, Role $role): User
    {
        return new User(
            email: $email,
            role: $role,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: 7,
        );
    }

    private function tokenFor(string $email): string
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['email' => $email, 'password' => self::PASSWORD])));
        $token = $this->decode($this->app->handle($request))['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function get(string $token, string $path): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('GET', $path)->withHeader('Authorization', 'Bearer ' . $token),
        );
    }

    private function put(string $token, string $path, mixed $body): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('PUT', $path)
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody(is_array($body) ? $body : []),
        );
    }

    private function post(string $token, string $path, mixed $body = []): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('POST', $path)
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody(is_array($body) ? $body : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    public function test_get_returns_defaults_when_no_settings_saved(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->get($token, '/admin/clear-settings');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(7, $body['dunning_min_interval_days']);
        self::assertSame('', $body['upstream_base_url']);
        self::assertSame([], $body['bank_accounts']);
        self::assertNull($body['fiscal_year_end_month']);
    }

    public function test_put_saves_fiscal_year_end_month(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $saved = $this->decode($this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'fiscal_year_end_month' => 3,
            'bank_accounts' => [],
        ]));
        self::assertSame(3, $saved['fiscal_year_end_month']);
        self::assertSame(3, $this->decode($this->get($token, '/admin/clear-settings'))['fiscal_year_end_month']);

        // Empty string clears it back to unset.
        $cleared = $this->decode($this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'fiscal_year_end_month' => '',
            'bank_accounts' => [],
        ]));
        self::assertNull($cleared['fiscal_year_end_month']);
    }

    public function test_put_rejects_out_of_range_fiscal_month(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'fiscal_year_end_month' => 13,
            'bank_accounts' => [],
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('fiscal_year_end_month', (string) $response->getBody());
    }

    public function test_put_saves_and_get_reflects_update(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->put($token, '/admin/clear-settings', [
            'upstream_base_url' => 'https://invoice.example.com',
            'upstream_token_ref' => 'NENE_INVOICE_TOKEN',
            'dunning_min_interval_days' => 14,
            'bank_accounts' => [
                [
                    'bank_name' => 'Test Bank',
                    'bank_branch' => 'Main',
                    'account_type' => 'ordinary',
                    'account_number' => '1234567',
                    'csv_encoding' => 'utf8',
                    'csv_date_format' => 'Y/m/d',
                    'csv_date_column' => 0,
                    'csv_amount_column' => 1,
                    'csv_counterparty_column' => 3,
                    'csv_header_rows' => 1,
                ],
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('https://invoice.example.com', $body['upstream_base_url']);
        self::assertSame(14, $body['dunning_min_interval_days']);
        self::assertCount(1, $body['bank_accounts']);
        self::assertSame('Test Bank', $body['bank_accounts'][0]['bank_name']);

        $getBody = $this->decode($this->get($token, '/admin/clear-settings'));
        self::assertSame(14, $getBody['dunning_min_interval_days']);
        self::assertCount(1, $getBody['bank_accounts']);
    }

    public function test_viewer_cannot_get_clear_settings(): void
    {
        $token = $this->tokenFor('viewer@acme.example');
        $response = $this->get($token, '/admin/clear-settings');

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_test_upstream_reachable(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/clear-settings/test-upstream');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decode($response)['reachable'] ?? false);
    }

    public function test_test_upstream_unreachable(): void
    {
        $this->invoiceClient->makeUnavailable();
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/clear-settings/test-upstream');

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->decode($response)['reachable'] ?? true);
    }

    public function test_invalid_dunning_interval_returns_422(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->put($token, '/admin/clear-settings', ['dunning_min_interval_days' => 0]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function test_scheduled_dunning_is_off_by_default(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $settings = $this->decode($this->get($token, '/admin/clear-settings'));

        // #400 §6: shipping this must not change what an existing deployment does.
        self::assertFalse($settings['is_dunning_schedule_enabled']);
        self::assertSame(3, $settings['dunning_initial_after_days']);
        self::assertSame(14, $settings['dunning_reminder_after_days']);
        self::assertSame(30, $settings['dunning_final_after_days']);
        self::assertSame(9, $settings['dunning_window_start_hour']);
        self::assertSame(18, $settings['dunning_window_end_hour']);
        self::assertTrue($settings['is_dunning_weekdays_only']);
        self::assertSame(50, $settings['dunning_max_per_run']);
    }

    public function test_put_saves_the_dunning_schedule_and_get_reflects_it(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $saved = $this->decode($this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'is_dunning_schedule_enabled' => true,
            'dunning_initial_after_days' => 5,
            'dunning_reminder_after_days' => 20,
            'dunning_final_after_days' => 45,
            'dunning_window_start_hour' => 10,
            'dunning_window_end_hour' => 17,
            'is_dunning_weekdays_only' => false,
            'dunning_max_per_run' => 25,
        ]));

        self::assertTrue($saved['is_dunning_schedule_enabled']);
        self::assertSame(45, $saved['dunning_final_after_days']);

        $fetched = $this->decode($this->get($token, '/admin/clear-settings'));
        self::assertTrue($fetched['is_dunning_schedule_enabled']);
        self::assertSame(10, $fetched['dunning_window_start_hour']);
        self::assertFalse($fetched['is_dunning_weekdays_only']);
        self::assertSame(25, $fetched['dunning_max_per_run']);
    }

    /**
     * 🔴 The #284 landmine, pinned by a test rather than only by prose.
     *
     * This endpoint is FULL-REPLACE. A PUT that omits a field resets it to the
     * default — it does not leave the stored value alone. A client that reads the
     * settings, changes one field and PUTs only that field will silently switch
     * scheduled dunning back off.
     *
     * Documented in `docs/development/clear-settings-full-replace.md`, but prose
     * is not enforcement: whoever writes the settings UI next (A2 F4) has to be
     * stopped by something that fails, not by something they might not read.
     */
    public function test_put_is_full_replace_so_an_omitted_field_is_reset_not_preserved(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'is_dunning_schedule_enabled' => true,
            'dunning_max_per_run' => 25,
        ]);

        self::assertTrue($this->decode($this->get($token, '/admin/clear-settings'))['is_dunning_schedule_enabled']);

        // A "partial" update that only touches the cap. The schedule flag is not
        // in the body — so it goes back to its default (off).
        $after = $this->decode($this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'dunning_max_per_run' => 10,
        ]));

        self::assertSame(10, $after['dunning_max_per_run']);
        self::assertFalse(
            $after['is_dunning_schedule_enabled'],
            'PUT is full-replace: an omitted field is reset to its default, not preserved.',
        );
    }

    public function test_a_window_that_never_opens_is_rejected(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        // start >= end means DunningSchedulePolicy treats the window as closed, so
        // saving it would silently stop dunning with nothing to explain why.
        $response = $this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'dunning_window_start_hour' => 18,
            'dunning_window_end_hour' => 9,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function test_non_ascending_stage_thresholds_are_rejected(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->put($token, '/admin/clear-settings', [
            'dunning_min_interval_days' => 7,
            'dunning_initial_after_days' => 30,
            'dunning_reminder_after_days' => 14,
            'dunning_final_after_days' => 3,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }
}
