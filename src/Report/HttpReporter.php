<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report;

use SickDoggy\MessengerMonitoring\Report\Model\MessageLifecycle;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class HttpReporter implements LifecycleReporter
{
    public function __construct(
        private HttpClientInterface $client,
        private string $endpoint,
        private ?string $apiKey = null,
        private float $timeout = 2.0,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(MessageLifecycle $lifecycle): void
    {
        try {
            $this->client->request('POST', $this->endpoint, [
                'headers' => array_filter([
                    'Content-Type' => 'application/json',
                    'X-Api-Key' => $this->apiKey,
                ]),
                'json' => $lifecycle->toArray(),
                'timeout' => $this->timeout,
            ])->getContent();
        } catch (Throwable $exception) {
            // raportowanie nie może złamać aplikacji, ale jego utrata musi być widoczna
            $this->logger?->warning('messenger-monitoring: publish failed', ['error' => $exception->getMessage()]);
        }
    }
}
