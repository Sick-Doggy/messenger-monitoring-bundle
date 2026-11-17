<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Attribute;

use Attribute;

/**
 * Tagi wystąpienia wiadomości — dowolne etykiety do filtrowania w store/UX.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Tags
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public array $tags,
    ) {
    }
}
