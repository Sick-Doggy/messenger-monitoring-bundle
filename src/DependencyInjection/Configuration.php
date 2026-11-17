<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use UnexpectedValueException;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('messenger_monitoring');
        $root = $treeBuilder->getRootNode();

        if (!$root instanceof ArrayNodeDefinition) {
            throw new UnexpectedValueException('Root node of messenger_monitoring must be an array node');
        }

        $children = $root->children();

        $children->scalarNode('application')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Nazwa aplikacji raportującej (rodzicielskiego serwisu); pozwala filtrować dane w store')
            ->end();

        $children->scalarNode('endpoint')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Adres POST migawek cyklu życia w store (API Platform)')
            ->end();

        $children->scalarNode('environment')
            ->defaultNull()
            ->info('Opcjonalne środowisko (prod/staging/dev); domyślnie puste')
            ->end();

        $children->scalarNode('api_key')
            ->defaultNull()
            ->end();

        $children->floatNode('timeout')
            ->defaultValue(2.0)
            ->end();

        $children->booleanNode('auto_register_bus_middleware')
            ->defaultTrue()
            ->info('Automatyczne dodanie MonitoringMiddleware do wszystkich busów messenger.bus.* (false — rejestracja ręczna w messenger.yaml)')
            ->end();

        return $treeBuilder;
    }
}
