<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Middleware;

use SickDoggy\MessengerMonitoring\Attribute\MessageContextFactory;
use SickDoggy\MessengerMonitoring\Attribute\PayloadResolver;
use SickDoggy\MessengerMonitoring\Attribute\ProcessIdResolver;
use SickDoggy\MessengerMonitoring\Context\ProcessContext;
use SickDoggy\MessengerMonitoring\MonitoredMessage;
use SickDoggy\MessengerMonitoring\Report\Model\MessageStage;
use SickDoggy\MessengerMonitoring\Report\StatusChangePublisher;
use SickDoggy\MessengerMonitoring\Report\Model\StatusEntry;
use SickDoggy\MessengerMonitoring\Stamp\MonitoringStamp;
use SickDoggy\MessengerMonitoring\Stamp\ProcessIdStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class MonitoringMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StatusChangePublisher $publisher,
        private MessageContextFactory $messageContextFactory,
        private PayloadResolver $payloadResolver,
        private ProcessIdResolver $processIdResolver,
        private ProcessContext $processContext,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->getMessage() instanceof MonitoredMessage) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (!$envelope->last(MonitoringStamp::class) instanceof MonitoringStamp) {
            return $this->monitorSend($envelope, $stack);
        }

        return $this->monitorConsume($envelope, $stack);
    }

    private function monitorSend(Envelope $envelope, StackInterface $stack): Envelope
    {
        $occurrenceId = Uuid::v4()->toRfc4122();
        $envelope = $envelope->with(new MonitoringStamp($occurrenceId));

        $processId = $this->resolveProcessId($envelope);
        if ($processId !== null) {
            $envelope = $envelope->with(new ProcessIdStamp($processId));
        }

        try {
            $result = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $exception) {
            $this->publish($occurrenceId, $envelope, MessageStage::SendFailed, $exception);
            throw $exception;
        }

        $this->publish($occurrenceId, $result, $this->syncStage($result));

        return $result;
    }

    private function monitorConsume(Envelope $envelope, StackInterface $stack): Envelope
    {
        $processStamp = $envelope->last(ProcessIdStamp::class);
        if (!$processStamp instanceof ProcessIdStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        // ambient procesu: handler może dispatchować kolejne wiadomości, które dziedziczą go
        $this->processContext->enter($processStamp->processId);
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->processContext->exit();
        }
    }

    private function resolveProcessId(Envelope $envelope): ?string
    {
        // priorytet: jawny stamp dewelopera > ambient (obecnie obsługiwany proces) > atrybut na message
        $explicitStamp = $envelope->last(ProcessIdStamp::class);
        if ($explicitStamp instanceof ProcessIdStamp) {
            return $explicitStamp->processId;
        }

        return $this->processContext->current() ?? $this->processIdResolver->resolve($envelope->getMessage());
    }

    private function publish(string $occurrenceId, Envelope $envelope, MessageStage $stage, ?Throwable $exception = null): void
    {
        $entry = StatusEntry::fromEnvelope($envelope, $stage);

        if ($exception !== null) {
            $entry = $entry->withError($exception);
        }

        // payload raportowany tylko na stanach producenta (tworzenie wystąpienia)
        $entry = $entry->withPayload($this->payloadResolver->resolve($envelope->getMessage()));

        $this->publisher->publish(
            $occurrenceId,
            $this->messageContextFactory->createFrom($envelope->getMessage()),
            $entry,
            $this->processId($envelope),
        );
    }

    private function processId(Envelope $envelope): ?string
    {
        return $envelope->last(ProcessIdStamp::class)?->processId;
    }

    private function syncStage(Envelope $envelope): MessageStage
    {
        return $envelope->last(SentStamp::class) instanceof SentStamp
            ? MessageStage::Sent
            : MessageStage::Handled;
    }
}
