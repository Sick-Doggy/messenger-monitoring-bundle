<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report;

use SickDoggy\MessengerMonitoring\Client\Exception\ApiClientException;
use SickDoggy\MessengerMonitoring\Client\Exception\MonitorNotFoundException;
use SickDoggy\MessengerMonitoring\Client\Model\Monitor;
use SickDoggy\MessengerMonitoring\Client\Model\MonitorState;
use SickDoggy\MessengerMonitoring\Client\StoreClient;
use SickDoggy\MessengerMonitoring\Report\Model\MessageLifecycle;
use SickDoggy\MessengerMonitoring\Report\Model\StatusEntry;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Failure-safe publikacja przez StoreClient: najpierw dopisanie stanu,
 * a upsert nagłówka dopiero na 404 (producent i konsument tak samo —
 * upsert jest idempotentny). Błąd trafia do loga (warning), nigdy wyjątek.
 */
final readonly class StoreLifecycleReporter implements LifecycleReporter
{
    public function __construct(
        private StoreClient $client,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(MessageLifecycle $lifecycle): void
    {
        try {
            $monitor = new Monitor(
                occurrenceId: $lifecycle->occurrenceId,
                className: $lifecycle->className,
                monitoringId: $lifecycle->monitoringId,
                processId: $lifecycle->processId,
                name: $lifecycle->name,
                tags: $lifecycle->tags,
            );

            foreach ($lifecycle->statuses as $status) {
                $this->appendState($monitor, $status);
            }
        } catch (Throwable $exception) {
            // raportowanie nie może złamać aplikacji, ale jego utrata musi być widoczna
            $this->logger?->warning('messenger-monitoring: publish failed', ['error' => $exception->getMessage()]);
        }
    }

    private function appendState(Monitor $monitor, StatusEntry $status): void
    {
        $state = $this->stateOf($status);

        try {
            $this->client->addMonitorState($monitor->occurrenceId, $state);
        } catch (MonitorNotFoundException) {
            $this->client->upsertMonitor($monitor);
            $this->client->addMonitorState($monitor->occurrenceId, $state);
        }
    }

    private function stateOf(StatusEntry $status): MonitorState
    {
        // payload nie wchodzi w spec store'a (zgłoszone zespołowi API) — pomijany
        return new MonitorState(
            stage: $status->stage,
            application: $status->application ?? '',
            occurredAt: $status->occurredAt,
            environment: $status->environment,
            busName: $status->busName,
            transportName: $status->transportName,
            handlerName: $status->handlerName,
            errorClass: $status->errorClass,
            errorMessage: $status->errorMessage,
        );
    }
}
