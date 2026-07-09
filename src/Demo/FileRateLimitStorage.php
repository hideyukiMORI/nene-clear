<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Http\ClockInterface;
use Nene2\Middleware\RateLimitStorageInterface;

/**
 * File-backed {@see RateLimitStorageInterface}: one JSON state file per key
 * (hashed) under `<baseDir>/rate-limits/`, mutated under an exclusive `flock`
 * (`Nene2\Demo` consumer, #275 — ported from the proven invoice concrete).
 *
 * Exists because the bundled {@see \Nene2\Middleware\InMemoryRateLimitStorage}
 * does not share state across PHP processes — on the Tier A shared-hosting
 * target (one process per request, no Redis/Memcached) an in-memory window
 * would simply never trigger. `var/` is the only writable runtime directory
 * there.
 *
 * Best-effort: when the file cannot be opened or locked the hit is counted as
 * the first of a fresh window (fail-open) and logged — the instance-wide org
 * ceiling still bounds total growth. Stale files are pruned by
 * `tools/sweep-demo.php`.
 */
final readonly class FileRateLimitStorage implements RateLimitStorageInterface
{
    public function __construct(
        private string $baseDir,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{count: int, reset_at: int}
     */
    public function hit(string $key, int $windowSeconds): array
    {
        $now = $this->clock->now()->getTimestamp();

        $dir = $this->baseDir . '/rate-limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            error_log(sprintf('NeNe Clear: could not persist rate-limit state at %s; this hit is not counted against the window.', $file));

            return ['count' => 1, 'reset_at' => $now + $windowSeconds];
        }

        try {
            flock($handle, LOCK_EX);

            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            $count = 1;
            $resetAt = $now + $windowSeconds;

            if (is_array($state) && isset($state['count'], $state['reset_at']) && (int) $state['reset_at'] > $now) {
                $count = (int) $state['count'] + 1;
                $resetAt = (int) $state['reset_at'];
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode(['count' => $count, 'reset_at' => $resetAt]));
            fflush($handle);

            return ['count' => $count, 'reset_at' => $resetAt];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
