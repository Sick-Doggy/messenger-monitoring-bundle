<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report;

use SickDoggy\MessengerMonitoring\Report\Model\MessageContext;
use SickDoggy\MessengerMonitoring\Report\Model\MessageLifecycle;
use SickDoggy\MessengerMonitoring\Report\Model\StatusEntry;
use Psr\Log\LoggerInterface;

/**
 * Wysyła do store'a POST przy każdej zmianie statusu wystąpienia —
 * jeden wpis w kolekcji na żądanie; store scala stany po occurrenceId.
 * Jedyny punkt przejścia wszystkich zmian stanu (producent i konsument) —
 * tu też jednoznaczne logowanie widoczne w `messenger:consume -v`.
 */
final readonly class StatusChangePublisher
{
    private const LOG_TEMPLATE = 'messenger-monitoring: {stage}';

    public function __construct(
        private readonly LifecycleReporter $reporter,
        private readonly string $application,
        private readonly ?string $environment = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(string $occurrenceId, MessageContext $context, StatusEntry $entry, ?string $processId = null): void
    {
        $this->logger?->log($entry->stage->logLevel(), self::LOG_TEMPLATE, [
            'stage' => $entry->stage->value,
            'occurrence_id' => $occurrenceId,
            'monitoring_id' => $context->monitoringId,
            'class' => $context->className,
            'name' => $context->name,
            'bus' => $entry->busName,
            'transport' => $entry->transportName,
            'handler' => $entry->handlerName,
            'process_id' => $processId,
        ]);

        $this->reporter->publish(new MessageLifecycle(
            occurrenceId: $occurrenceId,
            className: $context->className,
            monitoringId: $context->monitoringId,
            processId: $processId,
            name: $context->name,
            tags: $context->tags,
            statuses: [$entry->withContext($this->application, $this->environment)],
        ));
    }
}
