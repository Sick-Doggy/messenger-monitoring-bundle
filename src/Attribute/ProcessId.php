<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use Attribute;

/**
 * Deklaratywne źródło processId: nazwa publicznej metody (priorytet) lub
 * publicznego property — na klasie message, który SAM jest początkiem sprawy.
 * Sprawy zaczynane "z zewnątrz" (inny system, HTTP): stamp jawnie na dispatchu.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ProcessId
{
    public function __construct(
        public string $name,
    ) {
    }
}
