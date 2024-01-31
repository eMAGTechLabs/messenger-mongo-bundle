<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle;

use MongoDB\Client;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MongoTransportFactory implements TransportFactoryInterface
{
    private const DEFAULT_OPTIONS = [
        'collection' => 'messenger_queue',
        'queue' => 'default',
        'redeliver_timeout' => 3600,
        'enable_writeConcern_majority' => true
    ];

    private const DRIVER_OPTIONS_TYPE_MAP = [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
    ];

    public function createTransport(
        string $dsn,
        array $options,
        SerializerInterface $serializer
    ): TransportInterface {
        $uriOptions = [];
        if (isset($options['uriOptions'])) {
            $uriOptions = $options['uriOptions'];
            unset($options['uriOptions']);
        }

        $driverOptions = [];
        if (isset($options['driverOptions'])) {
            $driverOptions = $options['driverOptions'];
            unset($options['driverOptions']);
        }

        if (!is_array($uriOptions)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Option "uriOptions" has an invalid type. Expected array found %s',
                    gettype($uriOptions)
                )
            );
        }

        if (!is_array($driverOptions)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Option "driverOptions" has an invalid type. Expected array found %s',
                    gettype($driverOptions)
                )
            );
        }

        $driverOptions['typeMap'] = self::DRIVER_OPTIONS_TYPE_MAP;

        $configuration = $options + self::DEFAULT_OPTIONS;

        $client = new Client($dsn, $uriOptions, $driverOptions);
        $collection = $client->selectCollection($configuration['database'], $configuration['collection']);

        return new MongoTransport(
            $collection, $serializer, uniqid('consumer_', true), $configuration
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return (0 === strpos($dsn, 'mongodb://') || 0 === strpos($dsn, 'mongodb+srv://'));
    }
}
