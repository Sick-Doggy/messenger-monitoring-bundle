<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\DependencyInjection;

use SickDoggy\MessengerMonitoring\Middleware\MonitoringMiddleware;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wstrzykuje MonitoringMiddleware do wszystkich busów (messenger.bus.*) aplikacji,
 * dzięki czemu deweloper nie konfiguruje middleware ręcznie. Bus, na którym
 * middleware dodano jawnie w konfiguracji, jest pomijany.
 */
final class RegisterBusMiddlewarePass implements CompilerPassInterface
{
    private const MIDDLEWARE_SERVICE = MonitoringMiddleware::class;

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('messenger_monitoring.auto_register_bus_middleware') === false) {
            return;
        }

        if (!$container->hasDefinition(self::MIDDLEWARE_SERVICE)) {
            return;
        }

        // FrameworkExtension oznacza busów tagiem 'messenger.bus' (id = nazwa z configu,
        // prefiks 'messenger.bus.' nie jest gwarantowany). Prefiks zostaje jako fallback
        // dla busów budowanych ręcznie w starszych aplikacjach.
        $busIds = array_merge(
            array_keys($container->findTaggedServiceIds('messenger.bus')),
            preg_grep('/^messenger\.bus\./', array_keys($container->getDefinitions())) ?: [],
        );

        foreach (array_unique($busIds) as $busId) {
            $this->prependMiddleware($busId, $container->getDefinition($busId));
        }
    }

    private function prependMiddleware(string $busId, Definition $bus): void
    {
        if (\count($bus->getArguments()) === 0) {
            return;
        }

        $stack = $bus->getArgument(0);

        if ($stack instanceof IteratorArgument) {
            $services = $stack->getValues();
        } elseif (\is_array($stack)) {
            $services = $stack;
        } else {
            return;
        }

        if ($this->contains($services, self::MIDDLEWARE_SERVICE)) {
            return;
        }

        // stack build przez MessengerPass to lista surowych Reference
        array_unshift($services, new Reference(self::MIDDLEWARE_SERVICE));

        if ($stack instanceof IteratorArgument) {
            $stack->setValues($services);
        } else {
            $bus->replaceArgument(0, $services);
        }
    }

    /**
     * @param array<int, mixed> $services
     */
    private function contains(array $services, string $serviceId): bool
    {
        foreach ($services as $service) {
            if ($service instanceof Reference && $service->__toString() === $serviceId) {
                return true;
            }
        }

        return false;
    }
}
