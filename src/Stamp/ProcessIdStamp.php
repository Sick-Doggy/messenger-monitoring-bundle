<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Identyfikator procesu (sprawy) grupujący wiele wystąpień/message'ów.
 * Serializuje się w transporcie — przechodzi producer↔consumer jak MonitoringStamp.
 */
final readonly class ProcessIdStamp implements StampInterface
{
    public function __construct(
        public string $processId,
    ) {
    }
}
