<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

/**
 * RFC 6238 TOTP (Google Authenticator / Authy compatible). Adapted from the
 * NENE2 `howto/totp-authentication` recipe (reference impl NENE2-FT/totplog,
 * 32 tests incl. 12 vuln checks). Pure computation — replay protection,
 * lockout, storage, and encryption live in {@see TotpAuthenticator} and the
 * repositories.
 */
final class TotpGenerator
{
    private const int DIGITS = 6;
    private const int PERIOD = 30;
    private const string BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const string ISSUER = 'NeNe Clear';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** Public to let tests generate codes for specific steps. */
    public function computeCode(string $base32Secret, int $timeStep): string
    {
        $secret = $this->base32Decode($base32Secret);

        // Pack the time step as an 8-byte big-endian integer.
        $msg = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $msg, $secret, true);

        // Dynamic truncation (RFC 4226 §5.4).
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24)
              | ((ord($hash[$offset + 1]) & 0xFF) << 16)
              | ((ord($hash[$offset + 2]) & 0xFF) << 8)
              | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($code % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function currentTimeStep(): int
    {
        return (int) floor(time() / self::PERIOD);
    }

    /**
     * Verify a code within ±$window time steps; returns the matching step or
     * null. Uses hash_equals to avoid a timing oracle.
     */
    public function verify(string $base32Secret, string $code, int $window = 1): ?int
    {
        $t = $this->currentTimeStep();
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $t + $offset;
            if (hash_equals($this->computeCode($base32Secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function buildOtpAuthUri(string $secret, string $account): string
    {
        return 'otpauth://totp/' . rawurlencode(self::ISSUER) . ':' . rawurlencode($account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode(self::ISSUER)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    private function base32Encode(string $bytes): string
    {
        $output = '';
        $bitBuffer = 0;
        $bitCount = 0;
        foreach (str_split($bytes) as $byte) {
            $bitBuffer = ($bitBuffer << 8) | ord($byte);
            $bitCount += 8;
            while ($bitCount >= 5) {
                $bitCount -= 5;
                $output .= self::BASE32_CHARS[($bitBuffer >> $bitCount) & 0x1F];
            }
        }
        if ($bitCount > 0) {
            $output .= self::BASE32_CHARS[($bitBuffer << (5 - $bitCount)) & 0x1F];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(str_replace([' ', '-'], '', $encoded));
        $output = '';
        $bitBuffer = 0;
        $bitCount = 0;
        foreach (str_split($encoded) as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) {
                continue;
            }
            $bitBuffer = ($bitBuffer << 5) | $pos;
            $bitCount += 5;
            if ($bitCount >= 8) {
                $bitCount -= 8;
                $output .= chr(($bitBuffer >> $bitCount) & 0xFF);
            }
        }

        return $output;
    }
}
