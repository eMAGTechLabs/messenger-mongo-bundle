<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Unit;

use EmagTechLabs\MessengerMongoBundle\MongoTransport;
use EmagTechLabs\MessengerMongoBundle\Tests\Unit\Fixtures\HelloMessage;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoTransportTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldFetchAndDecodeADocumentFromDb(): void
    {
        $serializer = $this->createSerializer();
        $document = $this->createDocument();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOneAndUpdate')
            ->willReturn($document);

        $transport = new MongoTransport(
            $collection,
            $serializer,
            [
                'redeliver_timeout' => 3600,
                'queue' => 'default'
            ],
            'consumer_id'
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

    /**
     * @test
     */
    public function itShouldListAllMessages(): void
    {
        $serializer = $this->createSerializer();

        $collection = $this->createMock(Collection::class);
        $collection->method('find')
            ->willReturn([
                $this->createDocument(),
                $this->createDocument(),
                $this->createDocument(),
            ]);

        $transport = new MongoTransport($collection, $serializer, [], 'consumer_id');
        $collection = iterator_to_array($transport->all(2));

        $this->assertEquals(
            new HelloMessage('Hello'),
            $collection[0]->getMessage()
        );
    }

    /**
     * @test
     */
    public function itShouldFindAMessageById(): void
    {
        $serializer = $this->createSerializer();
        $document = $this->createDocument();

        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')
            ->willReturn($document);

        $transport = new MongoTransport($collection, $serializer, [], 'consumer_id');
        $envelope = $transport->find((string)(new ObjectId()));

        $this->assertEquals(
            new HelloMessage('Hello'),
            $envelope->getMessage()
        );
    }

    /**
     * @test
     */
    public function itShouldSendAMessage(): void
    {
        $collection = $this->createCollection();

        $transport = new MongoTransport(
            $collection,
            $this->createSerializer(),
            [
                'queue' => 'default'
            ],
            'consumer_id'
        );
        $envelope = $transport->send(
            (new Envelope(new HelloMessage('hello')))
                ->with(new DelayStamp(4000))
        );

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
        $this->assertSame(
            4,
            $collection->documents[0]['available_at']
                ->toDateTime()
                ->diff($collection->documents[0]['created_at']->toDateTime())
                ->s
        );
    }

    /**
     * @test
     */
    public function itShouldDeleteTheDocumentOnAckOrReject(): void
    {
        $documentId = new ObjectId();
        $envelope = (new Envelope(new HelloMessage('Hola!')))
            ->with(new TransportMessageIdStamp($documentId));

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(2))
            ->method('deleteOne')
            ->with(['_id' => $documentId]);

        $transport = new MongoTransport($collection, $this->createSerializer(), [], 'consumer_id');
        $transport->ack($envelope);
        $transport->reject($envelope);
    }

    private function createCollection(array $documents = []): Collection
    {
        return new class extends Collection {
            public $documents = [];

            public function __construct()
            {
            }

            public function insertOne($a, array $options = []): void
            {
                $this->documents[] = $a;
            }
        };
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
}
