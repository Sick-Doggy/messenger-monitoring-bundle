<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client\Model;

/**
 * Nagłówek wystąpienia — ciało POST /api/monitors (upsert po occurrenceId).
 * Odpowiednik MonitorInput w spec store'a.
 */
final readonly class Monitor
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $occurrenceId,
        public string $className,
        public ?string $monitoringId = null,
        public ?string $processId = null,
        public ?string $name = null,
        public array $tags = [],
    ) {
        if ($occurrenceId === '' || $className === '') {
            throw new \InvalidArgumentException('occurrenceId i className nie mogą być puste');
        }
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
            'processId' => $this->processId,
            'name' => $this->name,
            'tags' => $this->tags,
        ];
    }
}
