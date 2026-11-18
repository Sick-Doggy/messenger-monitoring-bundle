<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\EventSubscriber;

use SickDoggy\MessengerMonitoring\Context\ProcessContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Źródło procesu "od zewnątrz": nagłówek HTTP (domyślnie "Process-Id").
 * Listener na `kernel.request` ustawia wartość w procesie na czas requestu,
 * `kernel.finish_request` czyści ją. Wartość: trim + limit długości.
 */
final readonly class HttpProcessIdSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_HEADER = 'Process-Id';
    private const MAX_LENGTH = 255;

    public function __construct(
        private ProcessContext $processContext,
        private string $headerName = self::DEFAULT_HEADER,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
            KernelEvents::FINISH_REQUEST => 'onFinishRequest',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $value = $event->getRequest()->headers->get($this->headerName);

        if ($value === null || trim($value) === '') {
            return;
        }

        $this->processContext->enter($this->sanitize($value));
    }

    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->processContext->exit();
    }

    private function sanitize(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_LENGTH);
    }
}
