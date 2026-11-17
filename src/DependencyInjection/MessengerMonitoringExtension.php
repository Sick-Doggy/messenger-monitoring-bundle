<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\DependencyInjection;

use SickDoggy\MessengerMonitoring\Attribute\PayloadResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

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

        $this->registerPayloadResolver($container);
    }

    private function registerPayloadResolver(ContainerBuilder $container): void
    {
        $definition = new Definition(PayloadResolver::class);
        // IGNORE_ON_INVALID_REFERENCE: gdy 'serializer' nie istnieje w aplikacji,
        // argument jest pomijany i constructor przyjmie domyślne null
        $definition->setArgument('$serializer', new Reference('serializer', ContainerInterface::IGNORE_ON_INVALID_REFERENCE));
        $definition->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));

        $container->setDefinition(PayloadResolver::class, $definition);
    }

    public function getAlias(): string
    {
        return 'messenger_monitoring';
    }
}
