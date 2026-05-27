<?php

declare(strict_types=1);

namespace Spipu\CoreBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spipu\CoreBundle\Exception\ConnectionQuoterException;
use Spipu\CoreBundle\Service\ConnectionQuoter;

class ConnectionQuoterTest extends TestCase
{
    private function getConnectionWithPlatform(AbstractPlatform&MockObject $platform): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        return $connection;
    }

    public function testQuoteSingleIdentifierDelegatesToPlatform(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteSingleIdentifier')
            ->with('my_table')
            ->willReturn('`my_table`');

        $quoter = new ConnectionQuoter($this->getConnectionWithPlatform($platform));

        $this->assertSame('`my_table`', $quoter->quoteSingleIdentifier('my_table'));
    }

    public function testQuoteIdentifierWithSimpleName(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteSingleIdentifier')
            ->with('my_table')
            ->willReturn('`my_table`');

        $quoter = new ConnectionQuoter($this->getConnectionWithPlatform($platform));

        $this->assertSame('`my_table`', $quoter->quoteIdentifier('my_table'));
    }

    public function testQuoteIdentifierWithQualifiedName(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->exactly(2))
            ->method('quoteSingleIdentifier')
            ->willReturnCallback(
                fn(string $part): string => '`' . $part . '`'
            );

        $quoter = new ConnectionQuoter($this->getConnectionWithPlatform($platform));

        $this->assertSame('`my_schema`.`my_table`', $quoter->quoteIdentifier('my_schema.my_table'));
    }

    public function testQuoteIdentifierWithThreeParts(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->exactly(3))
            ->method('quoteSingleIdentifier')
            ->willReturnCallback(
                fn(string $part): string => '`' . $part . '`'
            );

        $quoter = new ConnectionQuoter($this->getConnectionWithPlatform($platform));

        $this->assertSame('`a`.`b`.`c`', $quoter->quoteIdentifier('a.b.c'));
    }

    public function testQuoteValueNullReturnsLiteralNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('quote');

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame('NULL', $quoter->quoteValue(null));
    }

    public function testQuoteValueBoolReturnsZeroOrOne(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('quote');

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame('1', $quoter->quoteValue(true));
        $this->assertSame('0', $quoter->quoteValue(false));
    }

    public function testQuoteValueIntegerReturnsString(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('quote');

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame('42', $quoter->quoteValue(42));
        $this->assertSame('-7', $quoter->quoteValue(-7));
        $this->assertSame('0', $quoter->quoteValue(0));
    }

    public function testQuoteValueFloatReturnsString(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('quote');

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame('3.14', $quoter->quoteValue(3.14));
    }

    public function testQuoteValueStringDelegatesToConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('quote')
            ->with("o'reilly")
            ->willReturn("'o\\'reilly'");

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame("'o\\'reilly'", $quoter->quoteValue("o'reilly"));
    }

    public function testQuoteValueAcceptsStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'hello';
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('quote')
            ->with('hello')
            ->willReturn("'hello'");

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame("'hello'", $quoter->quoteValue($stringable));
    }

    public function testQuoteValueThrowsOnArray(): void
    {
        $connection = $this->createMock(Connection::class);
        $quoter = new ConnectionQuoter($connection);

        $this->expectException(ConnectionQuoterException::class);
        $this->expectExceptionMessage('Cannot quote value of type array');
        $quoter->quoteValue([1, 2, 3]);
    }

    public function testQuoteValueThrowsOnNonStringableObject(): void
    {
        $connection = $this->createMock(Connection::class);
        $quoter = new ConnectionQuoter($connection);

        $this->expectException(ConnectionQuoterException::class);
        $quoter->quoteValue(new \stdClass());
    }

    public function testQuoteValuesJoinsWithComma(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('quote')
            ->willReturnCallback(fn(string $value): string => "'" . $value . "'");

        $quoter = new ConnectionQuoter($connection);

        $this->assertSame(
            "1,NULL,'foo',42,0",
            $quoter->quoteValues([true, null, 'foo', 42, false])
        );
    }

    public function testQuoteValuesThrowsOnEmptyArray(): void
    {
        $connection = $this->createMock(Connection::class);
        $quoter = new ConnectionQuoter($connection);

        $this->expectException(ConnectionQuoterException::class);
        $this->expectExceptionMessage('Cannot quote an empty list of values');
        $quoter->quoteValues([]);
    }
}
