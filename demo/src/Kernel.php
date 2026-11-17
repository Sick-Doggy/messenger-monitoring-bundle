<?php

declare(strict_types=1);

namespace App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MessengerMonitoring\MessengerMonitoringBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new MessengerMonitoringBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__.'/../config/{packages}/*.yaml');
        $container->import(__DIR__.'/../config/services.yaml');
    }
}
