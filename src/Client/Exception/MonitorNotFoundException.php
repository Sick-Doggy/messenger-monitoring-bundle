<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client\Exception;

/**
 * Store nie zna wystąpienia (HTTP 404 dla zasobu zagnieżdżonego pod Monitorem).
 * Sygnał dla wywołującego, że nagłówek musi być upsertnięty przed dopisaniem stanu.
 */
final class MonitorNotFoundException extends ApiClientException
{
    public function __construct(string $occurrenceId)
    {
        parent::__construct(sprintf('Monitor %s nie istnieje w store (HTTP 404) — wymagany upsert nagłówka', $occurrenceId));
    }
}
