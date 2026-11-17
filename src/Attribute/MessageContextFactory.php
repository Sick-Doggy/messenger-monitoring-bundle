<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use SickDoggy\MessengerMonitoring\Report\Model\MessageContext;

/**
 * Składa kontekst wiadomości ze wszystkich deklaratywnych atrybutów.
 */
final readonly class MessageContextFactory
{
    public function __construct(
        private MonitoringIdResolver $monitoringIdResolver,
        private NameResolver $nameResolver,
        private TagsResolver $tagsResolver,
    ) {
    }

    public function createFrom(object $message): MessageContext
    {
        return new MessageContext(
            className: get_class($message),
            monitoringId: $this->monitoringIdResolver->resolve($message),
            name: $this->nameResolver->resolve($message),
            tags: $this->tagsResolver->resolve($message),
        );
    }
}
