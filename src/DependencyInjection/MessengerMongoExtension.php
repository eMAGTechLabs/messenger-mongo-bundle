<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\DependencyInjection;

use EmagTechLabs\MessengerMongoBundle\MongoTransportFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class MessengerMongoExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $transportDefinition = new Definition(MongoTransportFactory::class);
        $transportDefinition->addTag('messenger.transport_factory');

        $container->setDefinition(MongoTransportFactory::class, $transportDefinition);
    }
}
