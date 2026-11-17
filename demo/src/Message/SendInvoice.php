<?php

declare(strict_types=1);

namespace App\Message;

use MessengerMonitoring\Attribute\MonitoringId;
use MessengerMonitoring\Attribute\Name;
use MessengerMonitoring\Attribute\Tags;
use MessengerMonitoring\MonitoredMessage;

// Deklaratywne "rozwiązania" (MessengerMonitoring\Attribute\):
// - MonitoringId: publiczne property LUB publiczna metoda zwracająca identyfikator biznesowy
// - Name: własna nazwa wystąpienia (wyświetlana zamiast nazwy klasy)
// - Tags: lista etykiet do filtrowania w store/UX
#[MonitoringId('invoiceId')]
#[Name('Wystawienie faktury')]
#[Tags(['billing', 'invoices'])]
final readonly class SendInvoice implements MonitoredMessage
{
    public function __construct(
        public string $invoiceId,
    ) {
    }
}
