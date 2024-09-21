<?php
namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/* @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter */
class ExceptionConverterTest extends FunctionalTestCase
{

    public function testConvert()
    {
        $this->expectException(SyntaxErrorException::class);
        $this->connection->executeQuery('INVALID SQL');
    }
}
