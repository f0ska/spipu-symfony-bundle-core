<?php

declare(strict_types=1);

namespace Spipu\CoreBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use Spipu\CoreBundle\Service\ConnectionQuoter;
use Spipu\CoreBundle\Service\ConnectionQuoterFactory;
use Spipu\CoreBundle\Service\ConnectionQuoterInterface;

class ConnectionQuoterFactoryTest extends TestCase
{
    public function testCreateReturnsConnectionQuoter(): void
    {
        $connection = $this->createMock(Connection::class);
        $factory = new ConnectionQuoterFactory();

        $quoter = $factory->create($connection);

        $this->assertInstanceOf(ConnectionQuoterInterface::class, $quoter);
        $this->assertInstanceOf(ConnectionQuoter::class, $quoter);
    }

    public function testCreatedQuoterIsBoundToGivenConnection(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteSingleIdentifier')
            ->with('foo')
            ->willReturn('`foo`');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $factory = new ConnectionQuoterFactory();
        $quoter = $factory->create($connection);

        $this->assertSame('`foo`', $quoter->quoteSingleIdentifier('foo'));
    }
}
