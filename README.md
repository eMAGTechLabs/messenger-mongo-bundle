# Messenger Mongo Bundle
[![Packagist Version](https://img.shields.io/packagist/v/emag-tech-labs/messenger-mongo-bundle)](https://packagist.org/packages/emag-tech-labs/messenger-mongo-bundle)
[![GA build](https://github.com/eMAGTechLabs/messenger-mongo-bundle/workflows/CI/badge.svg?branch=master)](https://github.com/eMAGTechLabs/messenger-mongo-bundle/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/eMAGTechLabs/messenger-mongo-bundle/branch/master/graph/badge.svg)](https://codecov.io/gh/eMAGTechLabs/messenger-mongo-bundle)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FeMAGTechLabs%2Fmessenger-mongo-bundle%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/eMAGTechLabs/messenger-mongo-bundle/master)    
This bundle offers a new type of transport for the Symfony Messenger component. While MongoDB is not the best tool to be used as a queue, sometimes it's more convenient to use the tools that your application already uses. For example, if your application already uses MongoDB and you want a persistent storage for the failed messages, I think it's quite handy to use MongoDB as a storage.  

### Installation
The recommended way to install the bundle is through Composer:  
```
$ composer require emag-tech-labs/messenger-mongo-bundle
```
### Configuration & usage
Take a look [here](https://docs.mongodb.com/php-library/current/reference/method/MongoDBClient__construct/) if you need to find out how to configure the **driverOptions**, **uriOptions** and **dsn** options.
```yaml
framework:
    messenger:
        transports:
            hello_queue:
                dsn: mongodb://127.0.0.1:27017
                options:
                    uriOptions: []
                    driverOptions: []
                    database: symfony # required
                    collection: hello_messages # default is "messenger_queue"
                    queue: hello_queue # default is "default"
                    redeliver_timeout: 4800 # default is 3600
                    enable_writeConcern_majority: false # default is true
```
The features described [here](https://symfony.com/doc/current/messenger.html#saving-retrying-failed-messages) can be used also, therefore the following commands are available in order to manually debug the failed messages:
```bash
# see all messages in the failure transport
$ php bin/console messenger:failed:show

# see details about a specific failed message
$ php bin/console messenger:failed:show 20 -vv

# view and retry messages one-by-one
$ php bin/console messenger:failed:retry -vv

# retry specific messages
$ php bin/console messenger:failed:retry 20 30 --force

# remove a message without retrying it
$ php bin/console messenger:failed:remove

# displays the number of queued messages in all transports
$ php bin/console messenger:stats
```
#### Defining an index for the messages collection
The MongoDB collections where messages are stored (default `messenger_queue`) should have the following index defined:
```js
{ available_at: 1 }
```

Without the index, the performance may degrade significantly with a large number of items (100k+) in the queue.
### Submitting bugs and feature requests
If you found a nasty bug or want to propose a new feature, you're welcome to open an issue or create a pull request [here](https://github.com/eMAGTechLabs/messenger-mongo-bundle/issues). 
