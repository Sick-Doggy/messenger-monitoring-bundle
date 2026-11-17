<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report\Model;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class StatusEntry
{
    public function __construct(
        public MessageStage $stage,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        public ?string $busName = null,
        public ?string $transportName = null,
        public ?string $handlerName = null,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
        public ?string $application = null,
        public ?string $environment = null,
        public ?string $payload = null,
    ) {
    }

    /**
     * Kontekst raportującego (application/environment) uzupełnia collector z configu.
     */
    public function withContext(string $application, ?string $environment): self
    {
        return new self(
            stage: $this->stage,
            occurredAt: $this->occurredAt,
            busName: $this->busName,
            transportName: $this->transportName,
            handlerName: $this->handlerName,
            errorClass: $this->errorClass,
            errorMessage: $this->errorMessage,
            application: $application,
            environment: $environment,
            payload: $this->payload,
        );
    }

    public static function fromEnvelope(Envelope $envelope, MessageStage $stage, ?string $transportName = null): self
    {
        return new self(
            stage: $stage,
            busName: $envelope->last(BusNameStamp::class)?->getBusName(),
            transportName: $transportName,
            handlerName: self::handlerNames($envelope),
        );
    }

    public function withError(\Throwable $exception): self
    {
        return new self(
            stage: $this->stage,
            occurredAt: $this->occurredAt,
            busName: $this->busName,
            transportName: $this->transportName,
            handlerName: $this->handlerName,
            errorClass: get_class($exception),
            errorMessage: $exception->getMessage(),
            payload: $this->payload,
        );
    }

    public function withPayload(?string $payload): self
    {
        return $this->payload === $payload ? $this : new self(
            stage: $this->stage,
            occurredAt: $this->occurredAt,
            busName: $this->busName,
            transportName: $this->transportName,
            handlerName: $this->handlerName,
            errorClass: $this->errorClass,
            errorMessage: $this->errorMessage,
            application: $this->application,
            environment: $this->environment,
            payload: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            // RFC3339_EXTENDED (mikrosekundy) — 'sent'+'received'+... często dzieją się w tej samej sekundzie,
            // a store składa oś czasu po occurredAt; second-precision gubiłoby kolejność.
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::RFC3339_EXTENDED),
            'application' => $this->application,
            'environment' => $this->environment,
            'busName' => $this->busName,
            'transportName' => $this->transportName,
            'handlerName' => $this->handlerName,
            'errorClass' => $this->errorClass,
            'errorMessage' => $this->errorMessage,
            'payload' => $this->payload,
        ];
    }

    private static function handlerNames(Envelope $envelope): ?string
    {
        $handlers = array_map(
            static fn (HandledStamp $stamp): string => $stamp->getHandlerName(),
            $envelope->all(HandledStamp::class),
        );

        return $handlers === [] ? null : implode(', ', $handlers);
    }
}
