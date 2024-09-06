<?php

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Schema\Types;

final class Money
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
