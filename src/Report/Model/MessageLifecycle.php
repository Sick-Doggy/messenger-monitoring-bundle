<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report\Model;

/**
 * Migawka wystąpienia: nagłówek (id + klasa) + kolekcja stanów z datami.
 */
final readonly class MessageLifecycle
{
    /**
     * @param list<string> $tags
     * @param StatusEntry[] $statuses
     */
    public function __construct(
        public string $occurrenceId,
        public string $className,
        public ?string $monitoringId = null,
        public ?string $name = null,
        public array $tags = [],
        public array $statuses = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
            'className' => $this->className,
            'monitoringId' => $this->monitoringId,
            'name' => $this->name,
            'tags' => $this->tags,
            'statuses' => array_map(
                static fn (StatusEntry $entry): array => $entry->toArray(),
                $this->statuses,
            ),
        ];
    }
}
