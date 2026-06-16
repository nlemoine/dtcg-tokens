<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withRootFiles()
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
    )
    // PHPUnit rules, three complementary layers:
    //   withComposerBased    - version-migration sets (phpunit40..125), gated on the installed PHPUnit version
    //   withAttributesSets   - annotation -> native attribute conversion (@dataProvider, @covers, ...)
    //   PHPUNIT_CODE_QUALITY - assertion/mock cleanups; listed explicitly because withComposerBased
    //                          loads only composer-version-triggered sets, not this plain set
    ->withComposerBased(phpunit: true)
    ->withAttributesSets(phpunit: true)
    ->withSets([
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ])
    ->withSkip([
        PreferPHPUnitThisCallRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        // Keep the readable `!== null` checks in CachedTokenFactory; this rule would
        // otherwise rewrite nullable-property null checks to `instanceof <FQCN>`.
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    ->withCache(cacheDirectory: __DIR__ . '/.rector.cache');
