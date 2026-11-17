<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use SensitiveParameter;
use Throwable;

class CrewBidVaultCrypto
{
    private const int MEMORY = 67_108_864;

    /** @return array<string, mixed> */
    public function seal(string $id, int $owner, #[SensitiveParameter] string $password, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $portalPassword): array
    {
        $key = random_bytes(32);
        try {
            $envelope = ['version' => 1, 'data_version' => bin2hex(random_bytes(16)), 'wrap_version' => 1];
            $nonce = random_bytes(24);
            $plain = json_encode(['employee' => $employee, 'password' => $portalPassword], JSON_THROW_ON_ERROR);
            try {
                $envelope['payload'] = base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $this->context($id, $owner, $envelope, false), $nonce, $key));
                $envelope['payload_nonce'] = base64_encode($nonce);
            } finally {
                sodium_memzero($plain);
            }

            return $this->wrap($id, $owner, $envelope, $key, $password);
        } finally {
            sodium_memzero($key);
        }
    }

    /** @param array<string, mixed> $envelope */
    public function unlock(string $id, int $owner, array $envelope, #[SensitiveParameter] string $password): string
    {
        $this->validate($envelope);
        $kek = sodium_crypto_pwhash(32, $password, $this->decode($envelope['salt'], 16), 2, self::MEMORY, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            $key = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($this->decode($envelope['wrapped_key'], 48), $this->context($id, $owner, $envelope, true), $this->decode($envelope['wrap_nonce'], 24), $kek);
            if ($key === false || strlen($key) !== 32) {
                throw $this->invalid();
            }

            return $key;
        } finally {
            sodium_memzero($kek);
        }
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array{employee: string, password: string}
     */
    public function credentials(string $id, int $owner, array $envelope, #[SensitiveParameter] string $key): array
    {
        $this->validate($envelope);
        $plain = false;
        try {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($this->decode($envelope['payload']), $this->context($id, $owner, $envelope, false), $this->decode($envelope['payload_nonce'], 24), $key);
            if ($plain === false) {
                throw $this->invalid();
            }
            $data = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data) || ! is_string($data['employee'] ?? null) || ! preg_match('/\A\d{1,20}\z/', $data['employee'])
                || ! is_string($data['password'] ?? null) || $data['password'] === '' || strlen($data['password']) > 256) {
                throw $this->invalid();
            }

            return ['employee' => $data['employee'], 'password' => $data['password']];
        } catch (Throwable) {
            throw $this->invalid();
        } finally {
            if (is_string($plain)) {
                sodium_memzero($plain);
            }
        }
    }

    /** @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public function rewrap(string $id, int $owner, array $envelope, #[SensitiveParameter] string $oldPassword, #[SensitiveParameter] string $newPassword): array
    {
        $key = $this->unlock($id, $owner, $envelope, $oldPassword);
        try {
            $this->credentials($id, $owner, $envelope, $key);
            $envelope['wrap_version']++;

            return $this->wrap($id, $owner, $envelope, $key, $newPassword);
        } finally {
            sodium_memzero($key);
        }
    }

    /** @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private function wrap(string $id, int $owner, array $envelope, #[SensitiveParameter] string $key, #[SensitiveParameter] string $password): array
    {
        $salt = random_bytes(16);
        $nonce = random_bytes(24);
        $kek = sodium_crypto_pwhash(32, $password, $salt, 2, self::MEMORY, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            return array_replace($envelope, [
                'salt' => base64_encode($salt), 'ops' => 2, 'memory' => self::MEMORY,
                'wrap_nonce' => base64_encode($nonce),
                'wrapped_key' => base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($key, $this->context($id, $owner, $envelope, true), $nonce, $kek)),
            ]);
        } finally {
            sodium_memzero($kek);
        }
    }

    /** @param array<string, mixed> $envelope */
    private function validate(array $envelope): void
    {
        if (($envelope['version'] ?? null) !== 1 || ($envelope['ops'] ?? null) !== 2 || ($envelope['memory'] ?? null) !== self::MEMORY
            || ! is_int($envelope['wrap_version'] ?? null) || $envelope['wrap_version'] < 1 || $envelope['wrap_version'] > 1_000_000
            || ! is_string($envelope['data_version'] ?? null) || ! preg_match('/\A[a-f0-9]{32}\z/', $envelope['data_version'])) {
            throw $this->invalid();
        }
        foreach (['salt' => 16, 'wrap_nonce' => 24, 'payload_nonce' => 24, 'wrapped_key' => 48, 'payload' => null] as $name => $length) {
            $this->decode($envelope[$name] ?? null, $length);
        }
    }

    /** @param array<string, mixed> $envelope */
    private function context(string $id, int $owner, array $envelope, bool $wrapping): string
    {
        return json_encode(['k4-vault', 1, $owner, $id, $envelope['data_version'], $wrapping ? 'key-wrap' : 'payload', $wrapping ? $envelope['wrap_version'] : 0], JSON_THROW_ON_ERROR);
    }

    private function decode(mixed $value, ?int $length = null): string
    {
        $decoded = is_string($value) && strlen($value) <= 4096 ? base64_decode($value, true) : false;
        if ($decoded === false || ($length !== null && strlen($decoded) !== $length) || ($length === null && strlen($decoded) < 16)) {
            throw $this->invalid();
        }

        return $decoded;
    }

    private function invalid(): BidPortalException
    {
        return new BidPortalException('The K4 connection could not be unlocked. Check your app password, or forget and reconnect.');
    }
}
