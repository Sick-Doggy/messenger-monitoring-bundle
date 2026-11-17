<?php

declare(strict_types=1);

namespace MessengerMonitoring\Attribute;

use Attribute;

/**
 * Wskazuje identyfikator biznesowy wiadomości: nazwę publicznej metody
 * (interpretowana przed property) lub publicznego property.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MonitoringId
{
    public function __construct(
        public string $name,
    ) {
    }
}
