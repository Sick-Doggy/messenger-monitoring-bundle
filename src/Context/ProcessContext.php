<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Context;

/**
 * Ambient procesu (sprawy) w bieżącym procesie: ustawiany przy obsłudze wiadomości
 * z ProcessIdStamp (consumer), czytany przy dispatchu nowych wiadomości (producer).
 * Worker obsługuje message sekwencyjnie; enter/exit są symetryczne (try/finally).
 */
final class ProcessContext
{
    private ?string $current = null;

    public function enter(string $processId): void
    {
        $this->current = $processId;
    }

    public function exit(): void
    {
        $this->current = null;
    }

    public function current(): ?string
    {
        return $this->current;
    }
}
