# Project Overview

This project is a Symfony Bundle that provides a MongoDB transport for the Symfony Messenger component. It allows developers to use MongoDB as a message queue for their Symfony applications. This is particularly useful when an application already uses MongoDB and wants to avoid adding another dependency for a message queue.

The core of the bundle is the `MongoTransport` class, which implements the Symfony `TransportInterface`. This class handles the sending, receiving, acknowledging, and rejecting of messages using a MongoDB collection.

The project uses Composer for dependency management, PHPUnit for testing, Infection for mutation testing, and PHPStan for static analysis.

# Building and Running

## Dependencies

Install dependencies using Composer:

```bash
composer install
```

## Running Tests

Run the test suite using PHPUnit:

```bash
vendor/bin/phpunit
```

## Static Analysis

Run PHPStan for static analysis:

```bash
vendor/bin/phpstan analyse src tests
```

## Mutation Testing

Run Infection for mutation testing:

```bash
vendor/bin/infection
```

# Development Conventions

## Coding Style

The project follows the PSR-12 coding style guide.

## Testing

The project has a comprehensive test suite using PHPUnit. Tests are located in the `tests` directory and are separated into unit and integration tests.

## Contribution

Contributions are welcome. Please open an issue or create a pull request on the [GitHub repository](https://github.com/eMAGTechLabs/messenger-mongo-bundle).
