<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Exceptions;

use Exception;

final class ConfigurationException extends Exception
{
    public static function noApiKey(): ConfigurationException
    {
        return new ConfigurationException(
            message: 'No API Key configured for OfficegestApiLogger. Ensure this is set before trying again.',
        );
    }
}
