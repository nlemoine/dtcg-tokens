<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Bridge\Symfony;

use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Twig\TokenExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class DtcgTokensBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->arrayNode('files')
            ->scalarPrototype()
            ->end()
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->end()
            ->scalarNode('cache')
            ->defaultNull()
            ->end()
            ->integerNode('ttl')
            ->defaultNull()
            ->min(1)
            ->info('Lifetime in seconds for cached tokens; set one when the pool survives deploys (Redis, APCu).')
            ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $rawFiles = $config['files'] ?? [];
        $files = [];
        if (\is_array($rawFiles)) {
            foreach ($rawFiles as $file) {
                if (\is_string($file)) {
                    $files[] = $file;
                }
            }
        }

        $rawCache = $config['cache'] ?? null;
        $cache = \is_string($rawCache) ? $rawCache : null;

        $rawTtl = $config['ttl'] ?? null;
        $ttl = \is_int($rawTtl) ? $rawTtl : null;

        $debug = (bool) $builder->getParameter('kernel.debug');

        $services = $container->services();

        $services->set(TokenParser::class);

        $services->set(JsonFileLoader::class)
            ->factory([JsonFileLoader::class, 'fromPaths'])
            ->args([$files]);

        $services->set(CachedTokenFactory::class)
            ->args([
                service(JsonFileLoader::class),
                $cache !== null ? service($cache) : null,
                $debug,
                service(TokenParser::class),
                $ttl,
            ]);

        $services->set(Tokens::class)
            ->factory([service(CachedTokenFactory::class), 'create']);

        if (! self::shouldIntegrateTwig(class_exists(Environment::class), class_exists(AttributeExtension::class))) {
            return;
        }

        $services->set(TokenExtension::class)
            ->args([service(Tokens::class)])
            ->tag('twig.runtime');

        $services->set('n5s_dtcg_tokens.twig_extension', AttributeExtension::class)
            ->args([TokenExtension::class])
            ->tag('twig.extension');
    }

    /**
     * Whether to register the Twig integration: no Twig means nothing to
     * integrate, and Twig without AttributeExtension (< 3.21) fails the
     * container build — disappearing silently would be worse than a clear
     * error.
     *
     * @internal Public for tests only: with Twig installed the class_exists()
     * probes cannot be faked, but the decision they feed can be exercised.
     */
    public static function shouldIntegrateTwig(bool $twigInstalled, bool $hasAttributeExtension): bool
    {
        if (! $twigInstalled) {
            return false;
        }

        if (! $hasAttributeExtension) {
            throw new \LogicException(
                'The n5s/dtcg-tokens Twig integration requires twig/twig >= 3.21 (Twig\Extension\AttributeExtension). Upgrade twig/twig, or remove it to skip the integration.',
            );
        }

        return true;
    }
}
