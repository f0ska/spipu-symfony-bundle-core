<?php

/**
 * This file is part of a Spipu Bundle
 *
 * (c) Laurent Minguet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spipu\CoreBundle\Service;

use Doctrine\DBAL\Connection;
use Spipu\CoreBundle\Exception\ConnectionQuoterException;
use Stringable;

final class ConnectionQuoter implements ConnectionQuoterInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function quoteIdentifier(string $identifier): string
    {
        return implode(
            '.',
            array_map(
                fn(string $part): string => $this->quoteSingleIdentifier($part),
                explode('.', $identifier)
            )
        );
    }

    public function quoteSingleIdentifier(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    public function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!is_string($value) && !$value instanceof Stringable) {
            throw new ConnectionQuoterException(
                sprintf('Cannot quote value of type %s', get_debug_type($value))
            );
        }

        return $this->connection->quote((string) $value);
    }

    public function quoteValues(array $values): string
    {
        if ($values === []) {
            throw new ConnectionQuoterException('Cannot quote an empty list of values');
        }

        return implode(
            ',',
            array_map(
                fn(mixed $value): string => $this->quoteValue($value),
                $values
            )
        );
    }
}
