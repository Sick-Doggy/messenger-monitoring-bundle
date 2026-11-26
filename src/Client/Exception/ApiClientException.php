<?php

declare(strict_types=1);

namespace SickDoggy\MessengerMonitoring\Client\Exception;

/**
 * Baza wyjątków klienta store'a: błędy transportu i odpowiedzi HTTP.
 * Warstwa wyżej (failure-safe) decyduje, co z nimi zrobić.
 */
class ApiClientException extends \RuntimeException
{
}
