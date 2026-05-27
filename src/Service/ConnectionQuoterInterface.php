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

interface ConnectionQuoterInterface
{
    public function quoteIdentifier(string $identifier): string;

    public function quoteSingleIdentifier(string $identifier): string;

    public function quoteValue(mixed $value): string;

    public function quoteValues(array $values): string;
}
