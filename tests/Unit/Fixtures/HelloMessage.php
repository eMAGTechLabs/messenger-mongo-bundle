<?php

declare(strict_types=1);

namespace EmagTechLabs\MessengerMongoBundle\Tests\Unit\Fixtures;

class HelloMessage
{
    /** @var string */
    public $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }
}
