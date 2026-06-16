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
use Twig\Extension\AttributeExtension;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class DtcgTokensBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->arrayNode('files')
            ->scalarPrototype()->end()
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->end()
            ->scalarNode('cache')->defaultNull()->end()
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
            ]);

        $services->set(Tokens::class)
            ->factory([service(CachedTokenFactory::class), 'create']);

        if (! class_exists(AttributeExtension::class)) {
            return;
        }

        $services->set(TokenExtension::class)
            ->args([service(Tokens::class)])
            ->tag('twig.runtime');

        $services->set('n5s_dtcg_tokens.twig_extension', AttributeExtension::class)
            ->args([TokenExtension::class])
            ->tag('twig.extension');
    }
}
