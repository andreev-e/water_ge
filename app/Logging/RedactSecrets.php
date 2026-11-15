<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Laravel "tap" hook for the log channels: adds RedactSecretsProcessor to them.
 */
class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactSecretsProcessor());
    }
}
