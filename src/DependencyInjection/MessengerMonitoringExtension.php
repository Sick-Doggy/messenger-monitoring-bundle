<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MessengerMonitoringExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('messenger_monitoring.application', $config['application']);
        $container->setParameter('messenger_monitoring.endpoint', $config['endpoint']);
        $container->setParameter('messenger_monitoring.environment', $config['environment']);
        $container->setParameter('messenger_monitoring.api_key', $config['api_key']);
        $container->setParameter('messenger_monitoring.timeout', $config['timeout']);
        $container->setParameter('messenger_monitoring.auto_register_bus_middleware', $config['auto_register_bus_middleware']);

        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config')))
            ->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'messenger_monitoring';
    }
}
