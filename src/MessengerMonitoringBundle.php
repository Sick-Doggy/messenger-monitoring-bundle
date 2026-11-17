<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring;

use SickDoggy\MessengerMonitoring\DependencyInjection\RegisterBusMiddlewarePass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MessengerMonitoringBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        // po MessengerPass, który składa stack busa z parametru $busId.'.middleware'
        $container->addCompilerPass(new RegisterBusMiddlewarePass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
