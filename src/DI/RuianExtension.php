<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\DI;

use Nette\DI\CompilerExtension;
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
class RuianExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'apiKey' => Expect::string()->required(),
            'cache' => Expect::structure([
                'enabled' => Expect::bool(true),
                'ttl' => Expect::int(86400)->min(0),
            ]),
        ]);
    }

    public function loadConfiguration(): void
    {
        $config = $this->config;
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('client'))
            ->setFactory(RuianClient::class, [
                'apiKey' => $config->apiKey,
                'cacheEnabled' => $config->cache->enabled,
                'cacheTtl' => $config->cache->ttl,
            ])
            ->setAutowired(true);
    }
}
