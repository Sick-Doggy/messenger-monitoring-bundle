<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client\Model;

/**
 * Wynik dopisania stanu. Duplikat (idempotentne trafienie) wraca z tym samym id.
 */
final readonly class StateResult
{
    public function __construct(
        public int $statusCode,
        public ?string $id,
    ) {
    }
}
