<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use Attribute;

/**
 * Zgoda na przesłanie zserializowanego message'a do store'a (statusy wytworzone
 * na stronie producenta). Bez określonych grup serializuje się całość w formacie
 * JSON; grupy (`Symfony\Component\Serializer` groups) ograniczają zakres pól.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Payload
{
    /**
     * @param list<string> $groups
     */
    public function __construct(
        public array $groups = [],
    ) {
    }
}
