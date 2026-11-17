<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Exception;

use RuntimeException;

final class PayloadAttributeException extends RuntimeException
{
    public static function missingSerializer(string $class): self
    {
        return new self(sprintf(
            'messenger-monitoring: class "%s" has the #[Payload] attribute, but Symfony Serializer is unavailable. Install symfony/serializer + symfony/property-access and enable "framework: serializer: { enabled: true }".',
            $class,
        ));
    }
}
