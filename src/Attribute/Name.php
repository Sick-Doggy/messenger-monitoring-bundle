<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use Attribute;

/**
 * Własna nazwa wystąpienia wiadomości (np. "Wystawienie faktury") —
 * zamiast nazwy klasy w store/UX.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Name
{
    public function __construct(
        public string $name,
    ) {
    }
}
