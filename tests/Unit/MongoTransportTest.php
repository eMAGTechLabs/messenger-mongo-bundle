<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Unit;

use EmagTechLabs\MessengerMongoBundle\MongoTransport;
use EmagTechLabs\MessengerMongoBundle\Tests\Unit\Fixtures\HelloMessage;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Server;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoTransportTest extends TestCase
{
    #[Test]
    public function itShouldCountTheMessages(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('countDocuments')
            ->willReturn(3);

        $transport = new MongoTransport(
            $collection,
            $this->createSerializer(),
            'consumer_id',
            []
        );

        $count = $transport->getMessageCount();

        $this->assertSame(3, $count);
    }
    #[Test]
    public function itShouldFetchAndDecodeADocumentFromDb(): void
    {
        $serializer = $this->createSerializer();
        $document = $this->createDocument();

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('findOneAndUpdate')
            ->with(
                $this->callback(function (array $filter) {
                    $this->assertArrayHasKey('$or', $filter);
                    $this->assertCount(2, $filter['$or']);
                    $this->assertEquals(['delivered_at' => null], $filter['$or'][0]);
                    $this->assertArrayHasKey('delivered_at', $filter['$or'][1]);
                    $this->assertArrayHasKey('$lt', $filter['$or'][1]['delivered_at']);
                    $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $filter['$or'][1]['delivered_at']['$lt']);

                    $this->assertEquals('default', $filter['queue_name']);
                    $this->assertArrayHasKey('$lte', $filter['available_at']);
                    return true;
                }),
                $this->callback(function (array $update) {
                    $this->assertArrayHasKey('$set', $update);
                    $this->assertEquals('consumer_id', $update['$set']['consumer_id']);
                    $this->assertArrayHasKey('delivered_at', $update['$set']);
                    return true;
                }),
                $this->callback(function (array $options) {
                    $this->assertEquals(['available_at' => 1], $options['sort']);
                    $this->assertEquals(\MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER, $options['returnDocument']);
                    $this->assertInstanceOf(\MongoDB\Driver\WriteConcern::class, $options['writeConcern']);
                    return true;
                })
            )
            ->willReturn($document);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id',
            [
                'redeliver_timeout' => 3600,
                'queue' => 'default',
                'enable_writeConcern_majority' => true
            ]
        );

        /** @var Envelope $envelope */
        $envelope = $transport->get()[0];

        $this->assertEquals(
            new HelloMessage('Hello'),
            $envelope->getMessage()
        );

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class);
        $this->assertEquals(
            $document['_id'],
            $transportMessageIdStamp->getId()
        );
    }

    #[Test]
    public function itShouldNothingIfConsumerIdNotMatching(): void
    {
        $serializer = $this->createSerializer();
        $document = $this->createDocument();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOneAndUpdate')
            ->willReturn($document);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id2',
            [
                'redeliver_timeout' => 3600,
                'queue' => 'default',
                'enable_writeConcern_majority' => true
            ]
        );

        $this->assertCount(0, $transport->get());
    }

    #[Test]
    public function itShouldNothingIfDocumentIsNotArray(): void
    {
        $serializer = $this->createSerializer();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOneAndUpdate')
            ->willReturn(null);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id2',
            [
                'redeliver_timeout' => 3600,
                'queue' => 'default',
                'enable_writeConcern_majority' => true
            ]
        );

        $this->assertCount(0, $transport->get());
    }

    #[Test]
    public function itShouldListAllMessages(): void
    {
        $serializer = $this->createSerializer();

        $collection = $this->createMock(Collection::class);
        $collection->method('find')
            ->willReturn($this->createCursor([
                $this->createDocument(),
                $this->createDocument(),
                $this->createDocument(),
            ]));

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id',
            []
        );
        $collection = iterator_to_array($transport->all(2));

        $this->assertEquals(
            new HelloMessage('Hello'),
            $collection[0]->getMessage()
        );
    }

    #[Test]
    public function itShouldFindAMessageById(): void
    {
        $serializer = $this->createSerializer();
        $document = $this->createDocument();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')
            ->willReturn($document);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id',
            []
        );
        $envelope = $transport->find((string)(new ObjectId()));

        $this->assertEquals(
            new HelloMessage('Hello'),
            $envelope->getMessage()
        );
    }

    #[Test]
    public function itShouldReturnNothingIfIdCouldNotBeFound(): void
    {
        $serializer = $this->createSerializer();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')
            ->willReturn(null);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            'consumer_id',
            []
        );
        $this->assertNull($transport->find(
            (string)(new ObjectId())
        ));
    }

    #[Test]
    public function itShouldSendAMessageWithDelay(): void
    {
        $collection = new class extends Collection {
            public array $documents = [];

            public function __construct()
            {
            }

            public function insertOne($document, array $options = []): InsertOneResult
            {
                $this->documents[] = $document;

                return new class extends InsertOneResult {
                    public function __construct()
                    {
                    }
                };
            }
        };

        $transport = new MongoTransport(
            $collection,
            $this->createSerializer(),
            'consumer_id',
            ['queue' => 'default']
        );
        $envelope = $transport->send(
            (new Envelope(new HelloMessage('hello')))
                ->with(new DelayStamp(4000))
        );

        $this->assertArrayHasKey('_id', $collection->documents[0]);
        $this->assertInstanceOf(ObjectId::class, $collection->documents[0]['_id']);
        $this->assertSame('{"text":"hello"}', $collection->documents[0]['body']);
        $this->assertEquals(
            [
                'type' => HelloMessage::class,
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\DelayStamp' => '[{"delay":4000}]',
                'Content-Type' => 'application/json',
            ],
            json_decode($collection->documents[0]['headers'], true)
        );
        $this->assertSame('default', $collection->documents[0]['queue_name']);
        $this->assertInstanceOf(TransportMessageIdStamp::class, $envelope->last(TransportMessageIdStamp::class));
        $this->assertEqualsWithDelta(
            4,
            $collection->documents[0]['available_at']
                ->toDateTime()
                ->getTimestamp() - $collection->documents[0]['created_at']->toDateTime()->getTimestamp(),
            1
        );
    }

    #[Test]
    public function itShouldSendAMessageWithoutDelay(): void
    {
        $collection = new class extends Collection {
            public array $documents = [];

            public function __construct()
            {
            }

            public function insertOne($document, array $options = []): InsertOneResult
            {
                $this->documents[] = $document;

                return new class extends InsertOneResult {
                    public function __construct()
                    {
                    }
                };
            }
        };

        $transport = new MongoTransport(
            $collection,
            $this->createSerializer(),
            'consumer_id',
            ['queue' => 'default']
        );
        $transport->send(new Envelope(new HelloMessage('hello')));

        $this->assertEqualsWithDelta(
            0,
            $collection->documents[0]['available_at']
                ->toDateTime()
                ->getTimestamp() - $collection->documents[0]['created_at']->toDateTime()->getTimestamp(),
            1
        );
    }

    #[Test]
    public function itShouldDeleteTheDocumentOnAckOrReject(): void
    {
        $documentId = new ObjectId();
        $envelope = (new Envelope(new HelloMessage('Hola!')))
            ->with(new TransportMessageIdStamp($documentId));

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(2))
            ->method('deleteOne')
            ->with(['_id' => $documentId]);

        $transport = new MongoTransport(
            $collection,
            $this->createSerializer(),
            'consumer_id',
            []
        );
        $transport->ack($envelope);
        $transport->reject($envelope);
    }

    private function createDocument(): array
    {
        return [
            '_id' => new ObjectId(),
            'body' => '{"text": "Hello"}',
            'headers' => [
                'type' => HelloMessage::class
            ],
            'consumer_id' => 'consumer_id',
        ];
    }

    private function createSerializer(): SerializerInterface
    {
        return new Serializer();
    }

    private function createCursor(array $documents): CursorInterface
    {
        return new class($documents) implements CursorInterface
        {
            private array $data;
            private int $position = 0;

            public function __construct(array $data)
            {
                $this->data = array_values($data);
            }

            public function current(): array|null|object
            {
                return $this->data[$this->position];
            }

            public function key(): int
            {
                return $this->position;
            }

            public function next(): void
            {
                $this->position++;
            }

            public function rewind(): void
            {
                $this->position = 0;
            }

            public function valid(): bool
            {
                return array_key_exists($this->position, $this->data);
            }

            public function toArray(): array
            {
                return $this->data;
            }

            public function isDead(): bool
            {
                return false;
            }

            public function setTypeMap(array $typemap): void
            {
            }

            public function getId(): Int64
            {
                throw new RuntimeException('Not implemented in test cursor.');
            }

            public function getServer(): Server
            {
                throw new RuntimeException('Not implemented in test cursor.');
            }
        };
    }
}
