<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use ReflectionClass;

final class TagsResolver
{
    /**
     * @var array<class-string, list<string>>
     */
    private array $cache = [];

    /**
     * @return list<string>
     */
    public function resolve(object $message): array
    {
        $class = get_class($message);

        if (!array_key_exists($class, $this->cache)) {
            $attributes = (new ReflectionClass($class))->getAttributes(Tags::class);

            if ($attributes === []) {
                $this->cache[$class] = [];

                return [];
            }

            /** @var Tags $instance */
            $instance = $attributes[0]->newInstance();
            $this->cache[$class] = array_map(
                'strval',
                $instance->tags,
            );

            return $this->cache[$class];
        }

        return $this->cache[$class];
    }
}
