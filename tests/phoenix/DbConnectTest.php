<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli;

class DbConnectTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.connect.php';
    }

    protected function tearDown(): void
    {
        // Tests below flip mysqli_report; restore the PHP 8.1+ default so
        // later test classes see the behaviour they expect.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        parent::tearDown();
    }

    public function testReturnsMysqliOnValidCredentials(): void
    {
        $result = \db_connect(self::$settings);

        $this->assertInstanceOf(mysqli::class, $result);
        mysqli_close($result);
    }

    public function testReturnsFalseUnderStrictModeOnBadCredentials(): void
    {
        // MYSQLI_REPORT_STRICT (the PHP 8.1+ default) makes mysqli_connect
        // throw mysqli_sql_exception. The function's catch block should
        // turn that back into a plain false return.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $bad = self::$settings;
        $bad['db_host'] = '127.0.0.1:1';            // closed port
        $bad['db_user'] = 'phoenix_no_such_user';
        $bad['db_pass'] = 'phoenix_wrong_password';
        $bad['db_name'] = 'phoenix_no_such_db';

        $this->assertFalse(\db_connect($bad));
    }

    public function testReturnsFalseUnderReportOffOnBadCredentials(): void
    {
        // MYSQLI_REPORT_OFF makes mysqli_connect return false directly
        // (no exception). The @ in the function suppresses the warning
        // and the early-return path fires instead of the catch.
        mysqli_report(MYSQLI_REPORT_OFF);

        $bad = self::$settings;
        $bad['db_host'] = '127.0.0.1:1';
        $bad['db_user'] = 'phoenix_no_such_user';
        $bad['db_pass'] = 'phoenix_wrong_password';
        $bad['db_name'] = 'phoenix_no_such_db';

        $this->assertFalse(\db_connect($bad));
    }

}
