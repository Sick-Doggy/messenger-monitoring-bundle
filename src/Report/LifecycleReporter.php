<?php

declare(strict_types=1);

namespace MessengerMonitoring\Report;

use MessengerMonitoring\Report\Model\MessageLifecycle;

interface LifecycleReporter
{
    public function publish(MessageLifecycle $lifecycle): void;
}
