<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class WidenBankAccountNumberForEncryption extends AbstractMigration
{
    /**
     * Encryption-at-rest (#192 / #195) stores `account_number` as
     * `enc:v1:` + base64(nonce + ciphertext), which is longer than the plain
     * value (a ~10-char number becomes ~75 chars; the 64-char max becomes
     * ~147). Widen the column so ciphertext fits. Legacy plaintext is
     * unaffected, and SQLite stores TEXT with no length limit so it is skipped.
     */
    public function up(): void
    {
        if ($this->getAdapter()->getAdapterType() === 'sqlite') {
            return;
        }

        $this->table('bank_accounts')
            ->changeColumn('account_number', 'string', ['limit' => 255, 'null' => false])
            ->update();
    }

    public function down(): void
    {
        if ($this->getAdapter()->getAdapterType() === 'sqlite') {
            return;
        }

        $this->table('bank_accounts')
            ->changeColumn('account_number', 'string', ['limit' => 64, 'null' => false])
            ->update();
    }
}
