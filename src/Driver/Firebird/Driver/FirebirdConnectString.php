<?php

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;

final class FirebirdConnectString
{

    private string $string;

    private function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param mixed[] $params
     * @throws HostDbnameRequired
     */
    public static function fromConnectionParameters(array $params): self
    {
        if (isset($params['connectstring'])) {
            return new self($params['connectstring']);
        }
        if (isset($params['host'], $params['dbname']) && $params['host'] !== '' && $params['dbname'] !== '') {
            $str = $params['host'];
            if (isset($params['port'])) {
                if ($params['port'] === '' || ! is_numeric($params['port'])) {
                    throw HostDbnameRequired::invalidPort();
                }
                $str .= '/' . $params['port'];
            }

            return new self( $str . (':' . $params['dbname']));
        }
        throw HostDbnameRequired::new();
    }
}
