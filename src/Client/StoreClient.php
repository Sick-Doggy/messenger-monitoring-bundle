<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client;

use SickDoggy\MessengerMonitoring\Client\Exception\ApiClientException;
use SickDoggy\MessengerMonitoring\Client\Exception\MonitorNotFoundException;
use SickDoggy\MessengerMonitoring\Client\Model\Monitor;
use SickDoggy\MessengerMonitoring\Client\Model\MonitorState;
use SickDoggy\MessengerMonitoring\Client\Model\StateResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Klient kontraktu HTTP store'a (spec v3): upsert nagłówka wystąpienia
 * i idempotentne dopisanie stanu. Niewiązany z DI — kolejność wywołań
 * (nagłówek przed stanem) leży po stronie konsumera.
 */
final readonly class StoreClient
{
    private const MONITORS_PATH = '/api/monitors';
    private const STATES_PATH_SUFFIX = '/states';
    private const ERROR_SNIPPET_LENGTH = 300;

    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $client,
        string $baseUrl,
        private string $apiKey,
        private float $timeout = 2.0,
        private ?LoggerInterface $logger = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * POST /api/monitors — upsert nagłówka po occurrenceId.
     */
    public function upsertMonitor(Monitor $monitor): void
    {
        [$status, $body] = $this->post(self::MONITORS_PATH, $monitor->toArray());

        $this->requestSucceeded($status, $body, self::MONITORS_PATH);
    }

    /**
     * POST /api/monitors/{occurrenceId}/states — idempotentne dopisanie stanu.
     * HTTP 404 oznacza nieznany nagłówek (MonitorNotFoundException) — moment
     * na fallback z upsertem nagłówka przed ponowną próbą.
     */
    public function addMonitorState(string $occurrenceId, MonitorState $state): StateResult
    {
        $path = sprintf('%s/%s%s', self::MONITORS_PATH, rawurlencode($occurrenceId), self::STATES_PATH_SUFFIX);

        [$status, $body] = $this->post($path, $state->toArray());

        if ($status === 404) {
            throw new MonitorNotFoundException($occurrenceId);
        }

        $this->requestSucceeded($status, $body, $path);

        return new StateResult($status, $this->resourceId($body));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{int, ?array<string, mixed>}
     */
    private function post(string $path, array $payload): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->client->request('POST', $this->baseUrl.$path, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Api-Key' => $this->apiKey,
                ],
                'json' => $payload,
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->logger?->error('messenger-monitoring: transport error', [
                'path' => $path,
                'duration_ms' => $this->durationMs($startedAt),
                'error' => $exception->getMessage(),
            ]);

            throw new ApiClientException(sprintf('POST %s błąd transportu: %s', $path, $exception->getMessage()), 0, $exception);
        }

        $this->logger?->debug('messenger-monitoring: POST {path} → HTTP {status}', [
            'path' => $path,
            'status' => $status,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        $decoded = $content === '' ? null : json_decode($content, true);

        return [$status, is_array($decoded) ? $decoded : null];
    }

    /**
     * @param ?array<string, mixed> $body
     */
    private function requestSucceeded(int $status, ?array $body, string $path): void
    {
        if ($status < 400) {
            return;
        }

        throw new ApiClientException(sprintf('POST %s: HTTP %d %s', $path, $status, $this->bodySnippet($body)));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @param ?array<string, mixed> $body
     */
    private function resourceId(?array $body): ?string
    {
        $id = $body['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @param ?array<string, mixed> $body
     */
    private function bodySnippet(?array $body): string
    {
        if ($body === null) {
            return '(brak ciała odpowiedzi)';
        }

        $encoded = json_encode($body) ?: '';

        return substr($encoded, 0, self::ERROR_SNIPPET_LENGTH);
    }
}
