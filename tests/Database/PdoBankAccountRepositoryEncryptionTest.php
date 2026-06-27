<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\Security\Encryptor;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoBankAccountRepositoryEncryptionTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-bank-enc-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createBankAccounts($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function account(string $number): BankAccount
    {
        return new BankAccount(
            organizationId: 7,
            bankName: 'みずほ銀行',
            bankBranch: '本店',
            accountType: AccountType::Ordinary,
            accountNumber: $number,
            csvEncoding: 'utf8',
            csvDateFormat: 'Y/m/d',
            csvDateColumn: 0,
            csvAmountColumn: 1,
            csvCounterpartyColumn: 3,
            csvHeaderRows: 1,
        );
    }

    private function storedNumber(int $id): string
    {
        $row = $this->query->fetchOne('SELECT account_number FROM bank_accounts WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return (string) $row['account_number'];
    }

    public function testAccountNumberIsEncryptedAtRestAndDecryptedOnRead(): void
    {
        $repo = new PdoBankAccountRepository($this->query, new Encryptor(self::key("\x02")));

        $id = $repo->save($this->account('1234567'));

        $stored = $this->storedNumber($id);
        self::assertStringStartsWith('enc:v1:', $stored);
        self::assertStringNotContainsString('1234567', $stored);

        $loaded = $repo->findById($id);
        self::assertNotNull($loaded);
        self::assertSame('1234567', $loaded->accountNumber);
    }

    public function testPlaintextWithoutKeyAndLegacyValuesStayReadable(): void
    {
        $plain = new PdoBankAccountRepository($this->query); // no encryptor
        $id = $plain->save($this->account('7654321'));

        self::assertSame('7654321', $this->storedNumber($id)); // stored as-is

        // A repo configured with a key still reads legacy plaintext (passthrough).
        $withKey = new PdoBankAccountRepository($this->query, new Encryptor(self::key("\x03")));
        $loaded = $withKey->findById($id);
        self::assertNotNull($loaded);
        self::assertSame('7654321', $loaded->accountNumber);
    }

    private static function key(string $byte): string
    {
        return base64_encode(str_repeat($byte, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
