<?php

declare(strict_types=1);

namespace App\Message;

use SickDoggy\MessengerMonitoring\Attribute\MonitoringId;
use SickDoggy\MessengerMonitoring\Attribute\Payload;
use SickDoggy\MessengerMonitoring\Attribute\ProcessId;
use SickDoggy\MessengerMonitoring\MonitoredMessage;

/**
 * Żywy przykład MUTOWALNEJ wiadomości: handler zmienia pola pomiędzy próbami.
 * Zauważ brak readonly — w przeciwieństwie do SendInvoice.
 * #[ProcessId('orderId')] — wiadomość SAM zaczyna sprawę (dispatch jest początkiem procesu).
 */
#[MonitoringId('orderId')]
#[ProcessId('orderId')]
#[Payload()]
final class ProcessInvoice implements MonitoredMessage
{
    public ?string $status = null;
    public int $handlerVisits = 0;

    public function __construct(
        public string $orderId,
    ) {
    }
}
