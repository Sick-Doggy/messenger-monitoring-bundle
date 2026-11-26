<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\InvoiceFollowUp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class InvoiceFollowUpHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(InvoiceFollowUp $message): void
    {
        $this->logger->notice('demo: follow-up handled', ['note' => $message->note]);
    }
}
