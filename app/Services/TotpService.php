<?php

namespace App\Services;

/**
 * Triển khai TOTP (RFC 6238) cho Google Authenticator — thuần PHP,
 * không cần extension bên ngoài (chỉ hash_hmac có sẵn).
 */
class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const WINDOW = 1; // cho phép lệch ±1 bước 30s

    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    public function otpauthUri(string $secret, string $email, string $issuer = 'SmashRank'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&period=%d&digits=%d&algorithm=SHA1',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            self::PERIOD,
            self::DIGITS
        );
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $slice = (int) floor(time() / self::PERIOD);
        for ($i = -$this->window(); $i <= $this->window(); $i++) {
            if (hash_equals($this->atCounter($secret, $slice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function window(): int
    {
        return self::WINDOW;
    }

    public function currentCode(string $secret): string
    {
        return $this->atCounter($secret, (int) floor(time() / self::PERIOD));
    }

    private function atCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32) ?? '');
        $bits = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}
