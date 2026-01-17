<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteRuian\Client\RuianClient;

/**
 * Nette DI Extension for RUIAN API Client
 *
 * Configuration example:
 *
 * ```neon
 * extensions:
 *     ruian: NksHub\NetteRuian\DI\RuianExtension
 *
 * ruian:
 *     apiKey: 'your-api-key'
 *     cache:
 *         enabled: true
 *         ttl: 86400
 * ```
 */
final class RuianExtension extends CompilerExtension
{
    private const int DEFAULT_CACHE_TTL = 86400;

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'apiKey' => Expect::string()->required(),
            'cache' => Expect::structure([
                'enabled' => Expect::bool(true),
                'ttl' => Expect::int(self::DEFAULT_CACHE_TTL)->min(0),
            ]),
        ]);
    }

    public function loadConfiguration(): void
    {
        /** @var object{apiKey: string, cache: object{enabled: bool, ttl: int}} $config */
        $config = $this->config;
        $builder = $this->getContainerBuilder();

        /** @var ServiceDefinition $definition */
        $definition = $builder->addDefinition($this->prefix('client'));
        $definition
            ->setFactory(RuianClient::class, [
                'apiKey' => $config->apiKey,
                'cacheEnabled' => $config->cache->enabled,
                'cacheTtl' => $config->cache->ttl,
            ])
            ->setAutowired();
    }
}
