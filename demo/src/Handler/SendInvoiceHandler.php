<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\SendInvoice;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendInvoiceHandler
{
    private static array $attemptsPerInvoice = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendInvoice $message): void
    {
        if (str_ends_with($message->invoiceId, 'FAIL')) {
            // za każdym razem wyjątek → 3 retry → failed-final
            throw new RuntimeException(sprintf('Faktura "%s" nie mogła zostać wystawiona', $message->invoiceId));
        }

        if (str_ends_with($message->invoiceId, 'RETRY')) {
            $attempts = self::$attemptsPerInvoice[$message->invoiceId] = (self::$attemptsPerInvoice[$message->invoiceId] ?? 0) + 1;
            if ($attempts < 3) {
                throw new RuntimeException(sprintf('Faktura "%s": próba %d — chwilowy błąd', $message->invoiceId, $attempts));
            }
        }

        $this->logger->notice('demo: invoice handled', ['invoiceId' => $message->invoiceId]);
    }
}
