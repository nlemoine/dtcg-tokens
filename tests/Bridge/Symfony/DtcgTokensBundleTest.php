<?php

declare(strict_types=1);

namespace n5s\DtcgTokens\Tests\Bridge\Symfony;

use n5s\DtcgTokens\Bridge\Symfony\DtcgTokensBundle;
use n5s\DtcgTokens\Cache\CachedTokenFactory;
use n5s\DtcgTokens\Loader\JsonFileLoader;
use n5s\DtcgTokens\Parser\TokenParser;
use n5s\DtcgTokens\Tokens;
use n5s\DtcgTokens\Twig\TokenExtension;
use n5s\DtcgTokens\Value\ColorValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extension\AttributeExtension;

#[CoversClass(DtcgTokensBundle::class)]
final class DtcgTokensBundleTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../fixtures/base.json';

    public function testTokensServiceResolvesParsedTokens(): void
    {
        $container = $this->compile([
            'files' => [self::FIXTURE],
        ]);

        $tokens = $container->get(Tokens::class);
        self::assertInstanceOf(Tokens::class, $tokens);

        $primary = $tokens->get('color.primary');
        self::assertInstanceOf(ColorValue::class, $primary);
        self::assertSame('#ff0000', $primary->toHex());
    }

    public function testCoreServicesAreWired(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
        ]);

        self::assertTrue($builder->hasDefinition(TokenParser::class));
        self::assertTrue($builder->hasDefinition(JsonFileLoader::class));
        self::assertTrue($builder->hasDefinition(CachedTokenFactory::class));
        self::assertTrue($builder->hasDefinition(Tokens::class));
    }

    public function testTwigServicesCarryTheExpectedTags(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
        ]);

        self::assertTrue($builder->hasDefinition(TokenExtension::class));
        $runtimeDefinition = $builder->getDefinition(TokenExtension::class);
        self::assertArrayHasKey('twig.runtime', $runtimeDefinition->getTags());
        self::assertInstanceOf(Reference::class, $runtimeDefinition->getArguments()[0]);
        self::assertSame(Tokens::class, (string) $runtimeDefinition->getArguments()[0]);

        $extensionId = 'n5s_dtcg_tokens.twig_extension';
        self::assertTrue($builder->hasDefinition($extensionId));
        $extensionDefinition = $builder->getDefinition($extensionId);
        self::assertSame(AttributeExtension::class, $extensionDefinition->getClass());
        self::assertArrayHasKey('twig.extension', $extensionDefinition->getTags());
        self::assertSame([TokenExtension::class], $extensionDefinition->getArguments());
    }

    public function testCacheConfigWiresPsr6PoolReference(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
            'cache' => 'some.cache.pool',
        ]);

        $arguments = $builder->getDefinition(CachedTokenFactory::class)->getArguments();

        // 2nd constructor argument is the optional PSR-6 cache pool.
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame('some.cache.pool', (string) $arguments[1]);
    }

    public function testOmittingCacheYieldsNullPoolArgument(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
        ]);

        $arguments = $builder->getDefinition(CachedTokenFactory::class)->getArguments();

        self::assertNull($arguments[1]);
    }

    public function testTtlConfigIsPassedToTheFactory(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
            'ttl' => 3600,
        ]);

        $arguments = $builder->getDefinition(CachedTokenFactory::class)->getArguments();

        // 5th constructor argument is the optional TTL.
        self::assertSame(3600, $arguments[4]);
    }

    public function testOmittingTtlYieldsNullTtlArgument(): void
    {
        $builder = $this->load([
            'files' => [self::FIXTURE],
        ]);

        self::assertNull($builder->getDefinition(CachedTokenFactory::class)->getArguments()[4]);
    }

    public function testTtlOfOneSecondIsAccepted(): void
    {
        // Boundary of min(1): the smallest meaningful lifetime must pass.
        $builder = $this->load([
            'files' => [self::FIXTURE],
            'ttl' => 1,
        ]);

        self::assertSame(1, $builder->getDefinition(CachedTokenFactory::class)->getArguments()[4]);
    }

    public function testNonPositiveTtlIsRejected(): void
    {
        // A zero or negative TTL would write an already-expired entry on
        // every request, making the pool pure overhead.
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'files' => [self::FIXTURE],
            'ttl' => 0,
        ]);
    }

    public function testTwigIntegrationIsSkippedWhenTwigIsAbsent(): void
    {
        // The class_exists() probes cannot be faked with Twig installed; the
        // decision they feed is exercised directly instead.
        self::assertFalse(DtcgTokensBundle::shouldIntegrateTwig(twigInstalled: false, hasAttributeExtension: false));
    }

    public function testTwigIntegrationIsEnabledOnModernTwig(): void
    {
        self::assertTrue(DtcgTokensBundle::shouldIntegrateTwig(twigInstalled: true, hasAttributeExtension: true));
    }

    public function testOutdatedTwigFailsTheContainerBuildWithAClearMessage(): void
    {
        // Twig installed but < 3.21: disappearing silently would be worse
        // than failing the container build.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('twig/twig >= 3.21');

        DtcgTokensBundle::shouldIntegrateTwig(twigInstalled: true, hasAttributeExtension: false);
    }

    public function testEmptyFilesListIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'files' => [],
        ]);
    }

    public function testMissingFilesKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $builder = $this->load($config);

        $builder->getDefinition(Tokens::class)->setPublic(true);
        $builder->compile();

        return $builder;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        // Standard kernel parameters AbstractBundle/ContainerConfigurator resolves
        // (Symfony 7.1 reads several of these eagerly; a real kernel always sets them).
        $builder->setParameter('kernel.debug', false);
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.project_dir', \dirname(__DIR__, 3));

        $bundle = new DtcgTokensBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $builder);

        return $builder;
    }
}
