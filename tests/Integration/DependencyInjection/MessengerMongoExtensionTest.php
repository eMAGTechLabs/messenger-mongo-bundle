<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Integration\DependencyInjection;

use EmagTechLabs\MessengerMongoBundle\DependencyInjection\MessengerMongoExtension;
use EmagTechLabs\MessengerMongoBundle\MongoTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MessengerMongoExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldRegisterAndTagTheTransportFactory(): void
    {
        $containerBuilder = self::createContainerBuilder();

        $this->assertTrue(
            $containerBuilder->hasDefinition(MongoTransportFactory::class)
        );

        $this->assertArrayHasKey(
            MongoTransportFactory::class,
            $containerBuilder->findTaggedServiceIds('messenger.transport_factory')
        );
    }

    private static function createContainerBuilder(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension(new MessengerMongoExtension());
        $containerBuilder->loadFromExtension('messenger_mongo');
        $containerBuilder->addCompilerPass(new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->findDefinition(MongoTransportFactory::class)
                    ->setPublic(true);
            }
        });
        $containerBuilder->compile();

        return $containerBuilder;
    }
}
