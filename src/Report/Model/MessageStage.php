<?php

declare(strict_types=1);

namespace MessengerMonitoring\Report\Model;

enum MessageStage: string
{
    case Sent = 'sent';
    case Received = 'received';
    case Handled = 'handled';
    case SendFailed = 'send-failed';
    case FailedRetry = 'failed-retry';
    case FailedFinal = 'failed-final';
}
