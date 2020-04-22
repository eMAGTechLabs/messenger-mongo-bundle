<?php

declare(strict_types=1);

namespace Iosifch\MessengerMongoBundle;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MongoTransportFactory implements TransportFactoryInterface
{
    private const DEFAULT_OPTIONS = [
        'collection' => 'messenger_queue',
        'queue' => 'default',
        'redeliver_timeout' => 3600
    ];

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function createTransport(
        string $dsn,
        array $options,
        SerializerInterface $serializer
    ): TransportInterface {
        if (false === $components = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Messenger DSN "%s" is invalid', $dsn));
        }

        if (isset($components['query'])) {
            parse_str($components['query'], $components['query']);
        }

        $configuration = ['connection' => $components['host']]
            + $options
            + $components['query']
            + self::DEFAULT_OPTIONS;

        try {
            /** @var DocumentManager $documentManager */
            $documentManager = $this->container->get(sprintf(
                'doctrine_mongodb.odm.%s_document_manager',
                $configuration['connection']
            ));
        } catch (ServiceNotFoundException $e) {
            throw new InvalidArgumentException(sprintf(
                'The given Document Manager "%s" not found',
                $configuration['connection']
            ));
        }

        $collection = $documentManager->getConnection()->selectCollection(
            $configuration['database'],
            $configuration['collection']
        );

        return new MongoTransport($collection, $serializer, $configuration);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'mongo://');
    }
}
