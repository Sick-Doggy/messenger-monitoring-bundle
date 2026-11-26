<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client\Model;

use SickDoggy\MessengerMonitoring\Report\Model\MessageStage;

/**
 * Wpis stanu cyklu życia — ciało POST /api/monitors/{occurrenceId}/states.
 * Odpowiednik MonitorStateInput w spec store'a; idempotencja po
 * (stage, occurredAt, application) leży po stronie store'a.
 */
final readonly class MonitorState
{
    public function __construct(
        public MessageStage $stage,
        public string $application,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        public ?string $environment = null,
        public ?string $busName = null,
        public ?string $transportName = null,
        public ?string $handlerName = null,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
    ) {
        if ($application === '') {
            throw new \InvalidArgumentException('application nie może być puste');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            // RFC3339_EXTENDED (mikrosekundy) — jak w StatusEntry::toArray()
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::RFC3339_EXTENDED),
            'application' => $this->application,
            'environment' => $this->environment,
            'busName' => $this->busName,
            'transportName' => $this->transportName,
            'handlerName' => $this->handlerName,
            'errorClass' => $this->errorClass,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
