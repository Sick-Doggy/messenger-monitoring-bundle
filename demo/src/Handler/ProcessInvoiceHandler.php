<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\ProcessInvoice;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessInvoiceHandler
{
    /**
     * Demonstracja mutacji: licznik per proces workera, tak jak w SendInvoiceHandler.
     *
     * @var array<string, int>
     */
    private static array $visits = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessInvoice $message): void
    {
        $message->handlerVisits = (self::$visits[$message->orderId] ?? 0) + 1;
        self::$visits[$message->orderId] = $message->handlerVisits;

        $message->status = sprintf('przetwarzam %d. raz', $message->handlerVisits);

        if ($message->handlerVisits < 2) {
            throw new RuntimeException(sprintf('"%s": chwilowy błąd (visits=%d)', $message->orderId, $message->handlerVisits));
        }

        $this->logger->notice('demo: invoice processed', ['orderId' => $message->orderId, 'visits' => $message->handlerVisits]);
    }
}
