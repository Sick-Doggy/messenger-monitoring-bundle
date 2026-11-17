<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report;

use SickDoggy\MessengerMonitoring\Report\Model\MessageLifecycle;

interface LifecycleReporter
{
    public function publish(MessageLifecycle $lifecycle): void;
}
