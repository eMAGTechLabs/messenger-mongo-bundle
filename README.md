# Messenger Mongo Bundle
![Packagist Version](https://img.shields.io/packagist/v/emag-tech-labs/messenger-mongo-bundle)
[![Build Status](https://travis-ci.org/eMAGTechLabs/messenger-mongo-bundle.svg?branch=master)](https://travis-ci.org/eMAGTechLabs/messenger-mongo-bundle)
[![codecov](https://codecov.io/gh/eMAGTechLabs/messenger-mongo-bundle/branch/master/graph/badge.svg)](https://codecov.io/gh/eMAGTechLabs/messenger-mongo-bundle)
    
This bundle offers a new type of transport for the Symfony Messenger component. While MongoDB is not the best tool to be used as a queue, sometimes it's more convenient to use the tools that your application already uses. For example, if your application already uses MongoDB and you want a persistent storage for the failed messages, I think it's quite handy to use MongoDB as a storage.  

> At this moment the bundle is still not ready for production, but it will be very soon!

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
```
The features described [here](https://symfony.com/doc/current/messenger.html#saving-retrying-failed-messages) can be used also, therefore the following commands are available in order to manually debug the failed messages:
```bash
# see all messages in the failure transport
$ bin/console messenger:failed:show

# see details about a specific failed message
$ php bin/console messenger:failed:show 20 -vv

# view and retry messages one-by-one
$ php bin/console messenger:failed:retry -vv

# retry specific messages
$ php bin/console messenger:failed:retry 20 30 --force

# remove a message without retrying it
$ bin/console messenger:failed:remove
``` 
### Submitting bugs and feature requests
If you found a nasty bug or want to propose a new feature, you're welcome to open an issue or create a pull request [here](https://github.com/eMAGTechLabs/messenger-mongo-bundle/issues). 

