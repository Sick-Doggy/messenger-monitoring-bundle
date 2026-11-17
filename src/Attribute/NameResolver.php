<?php

declare(strict_types=1);

namespace MessengerMonitoring\Attribute;

use ReflectionClass;

final class NameResolver
{
    /**
     * @var array<class-string, ?string>
     */
    private array $cache = [];

    public function resolve(object $message): ?string
    {
        $class = get_class($message);

        if (!array_key_exists($class, $this->cache)) {
            $attributes = (new ReflectionClass($class))->getAttributes(Name::class);
            $this->cache[$class] = $attributes === []
                ? null
                : $attributes[0]->newInstance()->name;
        }

        return $this->cache[$class];
    }
}
