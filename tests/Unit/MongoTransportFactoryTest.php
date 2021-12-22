<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Unit;

use EmagTechLabs\MessengerMongoBundle\MongoTransport;
use EmagTechLabs\MessengerMongoBundle\MongoTransportFactory;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use PHPUnit\Framework\TestCase;

class MongoTransportFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldSupportOnlyMongoSchema(): void
    {
        $factory = new MongoTransportFactory();

        $this->assertTrue($factory->supports('mongodb://default', []));
        $this->assertTrue($factory->supports('mongodb+srv://default', []));
        $this->assertFalse($factory->supports('doctrine://', []));
    }

    /**
     * @test
     */
    public function itShouldFailIfUriOptionsHasInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "uriOptions" has an invalid type. Expected array found boolean');

        $factory = new MongoTransportFactory();
        $factory->createTransport(
            'mongodb://127.0.0.1:27017',
            [
                'uriOptions' => false,
            ],
            $this->createMock(SerializerInterface::class)
        );
    }

    /**
     * @test
     */
    public function itShouldFailIfDriverOptionsHasInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "driverOptions" has an invalid type. Expected array found string');

        $factory = new MongoTransportFactory();
        $factory->createTransport(
            'mongodb://127.0.0.1:27017',
            [
                'driverOptions' => 'invalid',
            ],
            $this->createMock(SerializerInterface::class)
        );
    }

    /**
     * @test
     */
    public function itShouldCreateTransport(): void
    {
        $factory = new MongoTransportFactory();
        $transport = $factory->createTransport(
            'mongodb://120.0.0.1:27017',
            [
                'database' => 'symfony',
                'redeliver_timeout' => 1000,
                'collection' => 'failed_messages',
            ],
            $this->createMock(SerializerInterface::class)
        );

        $this->assertInstanceOf(
            MongoTransport::class,
            $transport
        );
    }
}
