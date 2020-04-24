<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Unit;

use Doctrine\MongoDB\Collection;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use EmagTechLabs\MessengerMongoBundle\MongoTransport;
use EmagTechLabs\MessengerMongoBundle\MongoTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoTransportFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldSupportOnlyMongoSchema(): void
    {
        $factory = new MongoTransportFactory(
            $this->createMock(ContainerInterface::class)
        );

        $this->assertTrue($factory->supports('mongo://default', []));
        $this->assertFalse($factory->supports('doctrine://', []));
    }

    /**
     * @test
     */
    public function itShouldThrowExceptionIfDocumentManagerNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Document Manager "default" not found');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willThrowException(new ServiceNotFoundException('doctrine_mongodb.odm.default_document_manager'));

        $factory = new MongoTransportFactory($container);

        $factory->createTransport(
            'mongo://default',
            [],
            $this->createMock(SerializerInterface::class)
        );
    }

    /**
     * @test
     */
    public function itShouldThrowExceptionIfDSNIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Messenger DSN "mongo:///" is invalid');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willThrowException(new ServiceNotFoundException('doctrine_mongodb.odm.default_document_manager'));

        $factory = new MongoTransportFactory($container);

        $factory->createTransport(
            'mongo:///',
            [],
            $this->createMock(SerializerInterface::class)
        );
    }

    /**
     * @test
     */
    public function itShouldCreateTransport(): void
    {
        $collection = $this->createMock(Collection::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('selectCollection')
            ->willReturn($collection);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->method('getConnection')
            ->willReturn($connection);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturn($documentManager);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new MongoTransportFactory($container);
        $transport = $factory->createTransport(
            'mongo://default?database=symfony&collection=failed_messages',
            [
                'redeliver_timeout' => 1000
            ],
            $serializer
        );

        $this->assertEquals(
            new MongoTransport(
                $collection,
                $serializer,
                [
                    'connection' => 'default',
                    'collection' => 'failed_messages',
                    'queue' => 'default',
                    'redeliver_timeout' => 1000,
                    'database' => 'symfony',
                ]
            ),
            $transport
        );
    }
}
