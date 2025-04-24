<?php

namespace Tgolsen\Ern3Indexer;

use Dotenv\Dotenv;

class EnvironmentHandler
{
    private $env;

    public function __construct(string $basePath)
    {
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();
        $this->env = $_ENV;
    }

    public function getEnvVariables(): array
    {
        return $this->env;
    }
}
