<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle;

use DateTime;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\FindOneAndUpdate;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

use function is_array;

final class MongoTransport implements TransportInterface, ListableReceiverInterface
{
    private Collection $collection;
    private SerializerInterface $serializer;
    private array $options;
    private string $consumerId;

    public function __construct(
        Collection $collection,
        SerializerInterface $serializer,
        string $consumerId,
        array $options = []
    ) {
        $this->serializer = $serializer;
        $this->options = $options;
        $this->collection = $collection;
        $this->consumerId = $consumerId;
    }

    public function get(): iterable
    {
        $now = new DateTime();
        $redeliverLimit = (clone $now)->modify(sprintf(
            '-%d seconds',
            $this->options['redeliver_timeout']
        ));

        $document = $this->collection->findOneAndUpdate(
            [
                '$or' => [
                    [
                        'delivered_at' => null,
                    ],
                    [
                        'delivered_at' => [
                            '$lt' => new UTCDateTime($redeliverLimit),
                        ],
                    ],
                ],
                'queue_name' => $this->options['queue'],
                'available_at' => [
                    '$lte' => new UTCDateTime($now),
                ],
            ],
            [
                '$set' => [
                    'consumer_id' => $this->consumerId,
                    'delivered_at' => new UTCDateTime($now),
                ],
            ],
            [
                'sort' => [
                    'available_at' => 1,
                ],
                'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        if (!is_array($document)) {
            return [];
        }

        if ($document['consumer_id'] !== $this->consumerId) {
            return [];
        }

        return [$this->createEnvelopeFromDocument($document)];
    }

    /**
     * @throws LogicException
     */
    private function removeMessage(Envelope $envelope): void
    {
        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class);

        if (!$transportMessageIdStamp instanceof TransportMessageIdStamp) {
            throw new LogicException(sprintf('No "%s" found on the Envelope.', TransportMessageIdStamp::class));
        }

        $this->collection->deleteOne([
            '_id' => $transportMessageIdStamp->getId(),
        ]);
    }

    /**
     * @throws LogicException
     */
    public function ack(Envelope $envelope): void
    {
        $this->removeMessage($envelope);
    }

    /**
     * @throws LogicException
     */
    public function reject(Envelope $envelope): void
    {
        $this->removeMessage($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = $delayStamp instanceof DelayStamp ? $delayStamp->getDelay() : 0;

        $now = new DateTime();
        $availableAt = (clone $now)->modify(sprintf('+%d seconds', $delay / 1000));

        $objectId = new ObjectId();
        $data = [
            '_id' => $objectId,
            'body' => $encodedMessage['body'],
            'headers' => json_encode($encodedMessage['headers'] ?? []),
            'queue_name' => $this->options['queue'],
            'created_at' => new UTCDateTime($now),
            'available_at' => new UTCDateTime($availableAt),
        ];

        $this->collection->insertOne($data);

        return $envelope->with(new TransportMessageIdStamp($objectId));
    }

    public function all(int $limit = null): iterable
    {
        $documents = $this->collection->find([], ['limit' => $limit]);

        foreach ($documents as $document) {
            yield $this->createEnvelopeFromDocument($document);
        }
    }

    public function find($id): ?Envelope
    {
        $document = $this->collection->findOne(['_id' => new ObjectId($id)]);

        if (null === $document) {
            return null;
        }

        return $this->createEnvelopeFromDocument($document);
    }

    /**
     * @throws MessageDecodingFailedException
     */
    private function createEnvelopeFromDocument(array $document): Envelope
    {
        $envelope = $this->serializer->decode(
            [
                'body' => $document['body'],
                'headers' => $document['headers'],
            ]
        );

        return $envelope->with(
            new TransportMessageIdStamp((string)$document['_id'])
        );
    }
}
