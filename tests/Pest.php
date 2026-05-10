<?php

use AichaDigital\Laratickets\Tests\Integration\Mysql\MysqlIntegrationTestCase;
use AichaDigital\Laratickets\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Configuration for Laratickets Package
|--------------------------------------------------------------------------
|
| Integration/Mysql is bound to its own TestCase first (more specific path),
| so its tests opt out of SQLite — they manage their own MySQL bootstrap,
| see MysqlIntegrationTestCase.
|
*/

uses(MysqlIntegrationTestCase::class)
    ->in('Integration/Mysql');

uses(TestCase::class)->in('Feature', 'Unit', 'ArchTest.php', 'ExampleTest.php');
