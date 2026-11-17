<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use SickDoggy\MessengerMonitoring\Exception\PayloadAttributeException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Serializuje message do JSON-a (Symfony Serializer) na podstawie #[Payload].
 * Serializer jest wstrzykiwany warunkowo przez rozszerzenie bundla (nullable).
 *
 * Zasady:
 * - brak atrybutu #[Payload] → null (brak zgody na serializację);
 * - atrybut jest, a serializator nie jest dostępny → twarde wyjątek PayloadAttributeException
 *   (błąd konfiguracji — developer ma go wykryć w testach, nie "przypadkiem" na produkcji);
 * - błąd normalizacji konkretnego payloadu → failure-safe (null + warning), bo zależy od danych.
 */
final class PayloadResolver
{
    /**
     * @var array<class-string, list<string>|null>
     */
    private array $groupsCache = [];

    public function __construct(
        private readonly ?SerializerInterface $serializer = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function resolve(object $message): ?string
    {
        $class = get_class($message);

        /** @var list<string>|null $groups */
        $groups = $this->groupsCache[$class] ??= $this->detectGroups($class);

        if ($groups === null) {
            return null;
        }

        if ($this->serializer === null) {
            throw PayloadAttributeException::missingSerializer($class);
        }

        try {
            $payload = $this->serializer->serialize($message, 'json', $groups === [] ? [] : ['groups' => $groups]);
        } catch (Throwable $exception) {
            $this->logger?->warning('messenger-monitoring: payload serialization failed', ['error' => $exception->getMessage()]);

            return null;
        }

        return (string) $payload;
    }

    /**
     * @param class-string $class
     * @return list<string>|null
     */
    private function detectGroups(string $class): ?array
    {
        $attributes = (new ReflectionClass($class))->getAttributes(Payload::class);

        if ($attributes === []) {
            return null;
        }

        /** @var Payload $instance */
        $instance = $attributes[0]->newInstance();

        return $instance->groups;
    }
}
