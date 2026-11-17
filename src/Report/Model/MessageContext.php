<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Report\Model;

/**
 * Deklaratywny kontekst wiadomości: wszystko, co o niej wiadomo poza klasą —
 * wypisywane w nagłówku payloadu na podstawie atrybutów z App\Attribute\.
 */
final readonly class MessageContext
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $className,
        public ?string $monitoringId = null,
        public ?string $name = null,
        public array $tags = [],
    ) {
    }
}
