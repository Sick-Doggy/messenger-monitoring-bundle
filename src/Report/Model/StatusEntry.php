<?php

declare(strict_types=1);

namespace MessengerMonitoring\Report\Model;

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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'application' => $this->application,
            'environment' => $this->environment,
            'busName' => $this->busName,
            'transportName' => $this->transportName,
            'handlerName' => $this->handlerName,
            'errorClass' => $this->errorClass,
            'errorMessage' => $this->errorMessage,
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
