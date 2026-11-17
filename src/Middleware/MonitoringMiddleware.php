<?php

declare(strict_types=1);

namespace MessengerMonitoring\Middleware;

use MessengerMonitoring\Attribute\MessageContextFactory;
use MessengerMonitoring\MonitoredMessage;
use MessengerMonitoring\Report\Model\MessageStage;
use MessengerMonitoring\Report\StatusChangePublisher;
use MessengerMonitoring\Report\Model\StatusEntry;
use MessengerMonitoring\Stamp\MonitoringStamp;
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

        // strona konsumpcji: korelacja już istnieje, stany raportuje subscriber
        return $stack->next()->handle($envelope, $stack);
    }

    private function monitorSend(Envelope $envelope, StackInterface $stack): Envelope
    {
        $occurrenceId = Uuid::v4()->toRfc4122();
        $envelope = $envelope->with(new MonitoringStamp($occurrenceId));

        try {
            $result = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $exception) {
            $this->publish($occurrenceId, $envelope, MessageStage::SendFailed, $exception);
            throw $exception;
        }

        $this->publish($occurrenceId, $result, $this->syncStage($result));

        return $result;
    }

    private function publish(string $occurrenceId, Envelope $envelope, MessageStage $stage, ?Throwable $exception = null): void
    {
        $entry = StatusEntry::fromEnvelope($envelope, $stage);

        $this->publisher->publish(
            $occurrenceId,
            $this->messageContextFactory->createFrom($envelope->getMessage()),
            $exception !== null ? $entry->withError($exception) : $entry,
        );
    }

    private function syncStage(Envelope $envelope): MessageStage
    {
        return $envelope->last(SentStamp::class) instanceof SentStamp
            ? MessageStage::Sent
            : MessageStage::Handled;
    }
}
