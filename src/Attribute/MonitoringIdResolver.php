<?php

declare(strict_types=1);

namespace MessengerMonitoring\Attribute;

use ReflectionClass;
use Throwable;

/**
 * Odczytuje monitoringId z message na podstawie atrybutu #[MonitoringId].
 * Źródło (metoda/property) jest rozwiązywane raz na klasę; błąd w konfiguracji
 * atrybutu nie psuje aplikacji — zwracany jest null.
 */
final class MonitoringIdResolver
{
    private const SOURCE_METHOD = 'method';
    private const SOURCE_PROPERTY = 'property';

    /**
     * @var array<class-string, array{type: 'method'|'property', name: string}|null>
     */
    private array $sources = [];

    public function resolve(object $message): ?string
    {
        $class = get_class($message);

        /** @var array{type: 'method'|'property', name: string}|null $source */
        $source = $this->sources[$class] ??= $this->detectSource($class);

        if ($source === null) {
            return null;
        }

        try {
            $value = match ($source['type']) {
                self::SOURCE_METHOD => $message->{$source['name']}(),
                self::SOURCE_PROPERTY => $message->{$source['name']},
            };
        } catch (Throwable) {
            return null;
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * @param class-string $class
     * @return array{type: 'method'|'property', name: string}|null
     */
    private function detectSource(string $class): ?array
    {
        $reflection = new ReflectionClass($class);

        $attributes = $reflection->getAttributes(MonitoringId::class);
        if ($attributes === []) {
            return null;
        }

        $name = $attributes[0]->newInstance()->name;

        if ($reflection->hasMethod($name) && $reflection->getMethod($name)->isPublic()) {
            return ['type' => self::SOURCE_METHOD, 'name' => $name];
        }

        if ($reflection->hasProperty($name) && $reflection->getProperty($name)->isPublic()) {
            return ['type' => self::SOURCE_PROPERTY, 'name' => $name];
        }

        return null;
    }
}
