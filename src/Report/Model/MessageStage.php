<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report\Model;

enum MessageStage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Handled = 'handled';
    case SendFailed = 'send-failed';
    case FailedRetry = 'failed-retry';
    case FailedFinal = 'failed-final';

    /**
     * Poziom PSR-3 dla wpisu loga przy tej zmianie stanu: cykl życia = info,
     * utrata stanu lub retry = warning, koniec z błędem = error.
     */
    public function logLevel(): string
    {
        return match ($this) {
            self::FailedFinal, self::SendFailed => 'error',
            self::FailedRetry => 'warning',
            default => 'info',
        };
    }
}
