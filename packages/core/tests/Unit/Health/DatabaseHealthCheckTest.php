<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Health;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\StatementInterface;
use Opora\Core\Health\DatabaseHealthCheck;
use PHPUnit\Framework\TestCase;

final class DatabaseHealthCheckTest extends TestCase
{
    public function test_check_returns_ok_when_database_responds(): void
    {
        $statement = $this->createMock(StatementInterface::class);
        $statement->expects(self::once())
            ->method('fetchAll')
            ->willReturn([['1' => '1']]);

        $database = $this->createMock(DatabaseInterface::class);
        $database->expects(self::once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($statement);

        $dbal = $this->createMock(DatabaseProviderInterface::class);
        $dbal->expects(self::once())
            ->method('database')
            ->willReturn($database);

        $databaseHealthCheck = new DatabaseHealthCheck($dbal);
        $healthCheckResult = $databaseHealthCheck->check();

        self::assertSame('ok', $healthCheckResult->status);
        self::assertNull($healthCheckResult->message);
        self::assertNotNull($healthCheckResult->latencyMs);
        self::assertGreaterThan(0.0, $healthCheckResult->latencyMs);
    }

    public function test_check_returns_error_when_database_throws(): void
    {
        $dbal = $this->createMock(DatabaseProviderInterface::class);
        $dbal->expects(self::once())
            ->method('database')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $databaseHealthCheck = new DatabaseHealthCheck($dbal);
        $healthCheckResult = $databaseHealthCheck->check();

        self::assertSame('error', $healthCheckResult->status);
        self::assertSame('Connection refused', $healthCheckResult->message);
        self::assertNull($healthCheckResult->latencyMs);
    }

    public function test_name_returns_database(): void
    {
        $dbal = $this->createMock(DatabaseProviderInterface::class);
        $databaseHealthCheck = new DatabaseHealthCheck($dbal);

        self::assertSame('database', $databaseHealthCheck->name());
    }
}
