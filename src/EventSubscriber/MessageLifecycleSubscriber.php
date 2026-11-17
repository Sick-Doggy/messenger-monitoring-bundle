<?php

declare(strict_types=1);

namespace MessengerMonitoring\EventSubscriber;

use MessengerMonitoring\Attribute\MessageContextFactory;
use MessengerMonitoring\MonitoredMessage;
use MessengerMonitoring\Report\Model\MessageStage;
use MessengerMonitoring\Report\StatusChangePublisher;
use MessengerMonitoring\Report\Model\StatusEntry;
use MessengerMonitoring\Stamp\MonitoringStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final readonly class MessageLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private StatusChangePublisher $publisher,
        private MessageContextFactory $messageContextFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onMessageReceived',
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->record($event, MessageStage::Received);
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->record($event, MessageStage::Handled);
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        $occurrence = $this->monitoredOccurrence($envelope);
        if ($occurrence === null) {
            return;
        }

        $stage = $event->willRetry() ? MessageStage::FailedRetry : MessageStage::FailedFinal;

        $this->publish(
            $occurrence->occurrenceId,
            $envelope,
            StatusEntry::fromEnvelope($envelope, $stage, $event->getReceiverName())
                ->withError($event->getThrowable()),
        );
    }

    private function record(WorkerMessageReceivedEvent|WorkerMessageHandledEvent $event, MessageStage $stage): void
    {
        $envelope = $event->getEnvelope();

        $occurrence = $this->monitoredOccurrence($envelope);
        if ($occurrence === null) {
            return;
        }

        $this->publish(
            $occurrence->occurrenceId,
            $envelope,
            StatusEntry::fromEnvelope($envelope, $stage, $event->getReceiverName()),
        );
    }

    private function publish(string $occurrenceId, Envelope $envelope, StatusEntry $entry): void
    {
        $this->publisher->publish(
            $occurrenceId,
            $this->messageContextFactory->createFrom($envelope->getMessage()),
            $entry,
        );
    }

    private function monitoredOccurrence(Envelope $envelope): ?MonitoringStamp
    {
        if (!$envelope->getMessage() instanceof MonitoredMessage) {
            return null;
        }

        return $envelope->last(MonitoringStamp::class);
    }
}
