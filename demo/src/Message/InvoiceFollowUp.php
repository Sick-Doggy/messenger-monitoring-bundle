<?php

declare(strict_types=1);

namespace App\Message;

use SickDoggy\MessengerMonitoring\MonitoredMessage;

/**
 * Wiadomość-kontynuacja: NIE deklaruje #[ProcessId] — processId powinien
 * przylecieć "sam" przez ambiente (parent ProcessInvoice jest na stosie
 * w momencie dispatchu). To jest całe sedno demo propagacji procesu.
 */
final readonly class InvoiceFollowUp implements MonitoredMessage
{
    public function __construct(
        public string $note,
    ) {
    }
}
