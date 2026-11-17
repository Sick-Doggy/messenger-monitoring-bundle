<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report;

use SickDoggy\MessengerMonitoring\Report\Model\MessageContext;
use SickDoggy\MessengerMonitoring\Report\Model\MessageLifecycle;
use SickDoggy\MessengerMonitoring\Report\Model\StatusEntry;

/**
 * Wysyła do store'a POST przy każdej zmianie statusu wystąpienia —
 * jeden wpis w kolekcji na żądanie; store scala stany po occurrenceId.
 */
final readonly class StatusChangePublisher
{
    public function __construct(
        private readonly LifecycleReporter $reporter,
        private readonly string $application,
        private readonly ?string $environment = null,
    ) {
    }

    public function publish(string $occurrenceId, MessageContext $context, StatusEntry $entry): void
    {
        $this->reporter->publish(new MessageLifecycle(
            occurrenceId: $occurrenceId,
            className: $context->className,
            monitoringId: $context->monitoringId,
            name: $context->name,
            tags: $context->tags,
            statuses: [$entry->withContext($this->application, $this->environment)],
        ));
    }
}
