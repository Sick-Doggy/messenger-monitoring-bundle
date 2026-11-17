<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Korelacyjny identyfikator wystąpienia wiadomości; serializuje się w transporcie,
 * więc producent i konsument raportują to samo wystąpienie.
 */
final readonly class MonitoringStamp implements StampInterface
{
    public function __construct(
        public string $occurrenceId,
    ) {
    }
}
