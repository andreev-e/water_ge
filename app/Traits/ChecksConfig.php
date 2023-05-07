<?php

namespace App\Traits;

use InvalidArgumentException;

trait ChecksConfig
{
    protected function checkConfig(array $params, array $config): void
    {
        collect($params)->each(function ($param) use ($config) {
            if (!isset($config[$param])) {
                throw new InvalidArgumentException('[' . $param . '] parameter must be present in the configuration.');
            }

            if (empty($config[$param])) {
                throw new InvalidArgumentException('[' . $param . '] parameter cannot be empty.');
            }
        });
    }
}
