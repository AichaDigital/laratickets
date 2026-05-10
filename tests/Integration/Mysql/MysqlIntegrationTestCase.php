<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Tests\Integration\Mysql;

use AichaDigital\Laratickets\LaraticketsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for MySQL integration (UUID-first contract).
 *
 * Why this extends Orchestra\Testbench\TestCase directly (not tests/TestCase.php):
 *   - tests/TestCase.php hard-codes SQLite in-memory and does not provision a
 *     `users` table; here we control the connection and create users with UUID
 *     primary key, then run the package migration set via artisan.
 *
 * Skip semantics: if any of LARATICKETS_TEST_MYSQL_* env vars is missing the
 * test is markTestSkipped() with a clear message — the suite stays green
 * in environments without MySQL.
 *
 * Mirrors larabill/tests/Integration/Mysql/MysqlIntegrationTestCase.php
 * (simplified — no per-dataset id-type matrix; laratickets is UUID-only).
 *
 * @see docs/ADR-001-uuid-first.md
 */
abstract class MysqlIntegrationTestCase extends Orchestra
{
    /**
     * @var array<int, string>
     */
    private const REQUIRED_ENV = [
        'LARATICKETS_TEST_MYSQL_HOST',
        'LARATICKETS_TEST_MYSQL_PORT',
        'LARATICKETS_TEST_MYSQL_DATABASE',
        'LARATICKETS_TEST_MYSQL_USERNAME',
        'LARATICKETS_TEST_MYSQL_PASSWORD',
    ];

    protected function setUp(): void
    {
        if (! self::mysqlEnvAvailable()) {
            $this->markTestSkipped(
                'MySQL integration env not configured. Set LARATICKETS_TEST_MYSQL_HOST, '
                .'LARATICKETS_TEST_MYSQL_PORT, LARATICKETS_TEST_MYSQL_DATABASE, '
                .'LARATICKETS_TEST_MYSQL_USERNAME, LARATICKETS_TEST_MYSQL_PASSWORD to run.'
            );
        }

        parent::setUp();

        $this->dropAllTables();
    }

    protected function tearDown(): void
    {
        if (self::mysqlEnvAvailable()) {
            $this->dropAllTables();
        }

        parent::tearDown();
    }

    public function getEnvironmentSetUp($app)
    {
        if (! self::mysqlEnvAvailable()) {
            return;
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'host' => env('LARATICKETS_TEST_MYSQL_HOST'),
            'port' => (int) env('LARATICKETS_TEST_MYSQL_PORT'),
            'database' => env('LARATICKETS_TEST_MYSQL_DATABASE'),
            'username' => env('LARATICKETS_TEST_MYSQL_USERNAME'),
            'password' => env('LARATICKETS_TEST_MYSQL_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function getPackageProviders($app)
    {
        return [LaraticketsServiceProvider::class];
    }

    /**
     * Provision a UUID-keyed `users` table and run the package migrations.
     *
     * The package emits all 9 user-FK columns as char(36) via MigrationHelper;
     * this verifies the chain end-to-end against a real MySQL instance.
     */
    protected function bootstrap(): void
    {
        $this->createUsersTable();

        $this->artisan('migrate', ['--database' => 'testing'])->assertExitCode(0);
    }

    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }

    public static function mysqlEnvAvailable(): bool
    {
        foreach (self::REQUIRED_ENV as $key) {
            if (env($key) === null || env($key) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop every table in the test database. Uses FK-checks bypass so order
     * does not matter — robust against arbitrary FK graphs.
     */
    protected function dropAllTables(): void
    {
        $database = env('LARATICKETS_TEST_MYSQL_DATABASE');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = DB::select(
                'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$database]
            );

            foreach ($tables as $row) {
                DB::statement("DROP TABLE IF EXISTS `{$row->name}`");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function getMysqlColumnType(string $table, string $column): string
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('LARATICKETS_TEST_MYSQL_DATABASE'), $table, $column]
        );

        return $row !== null ? strtolower($row->DATA_TYPE) : '';
    }

    protected function getMysqlColumnLength(string $table, string $column): ?int
    {
        $row = DB::selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('LARATICKETS_TEST_MYSQL_DATABASE'), $table, $column]
        );

        return $row !== null && $row->len !== null ? (int) $row->len : null;
    }
}
