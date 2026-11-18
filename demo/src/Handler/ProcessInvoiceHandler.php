<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\InvoiceFollowUp;
use App\Message\ProcessInvoice;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly MessageBusInterface $bus,
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

        // kontynuacja procesu: InvoiceFollowUp nie deklaruje processId —
        // ma go dostać z ambiente (dispatch podczas obsługi rodzica)
        $this->bus->dispatch(new InvoiceFollowUp(sprintf('follow-up do sprawy "%s"', $message->orderId)));

        $this->logger->notice('demo: invoice processed', ['orderId' => $message->orderId, 'visits' => $message->handlerVisits]);
    }
}
