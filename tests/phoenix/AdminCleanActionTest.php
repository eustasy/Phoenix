<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminCleanActionTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.clean.php';
    }

    public function testReturnsSuccessMessageOnCleanSuccess(): void
    {
        // admin_clean_action delegates to task_clean, which deletes its own
        // sentinel rows and so always succeeds against a healthy DB.
        $result = admin_clean_action(self::$connection, self::$settings, self::$time);
        $this->assertSame('The peers list has been cleaned.', $result);
    }

    public function testReturnsFailureMessageWhenCleanFails(): void
    {
        // Force task_clean to fail by pointing the settings at a table prefix
        // that does not exist; the underlying DELETE returns false and bubbles
        // up as the failure-message branch. PHP 8.1+ mysqli throws by default,
        // so silence reporting for this case.
        $brokenSettings = self::$settings;
        $brokenSettings['db_prefix'] = '__phoenix_missing_prefix_';

        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $result = admin_clean_action(self::$connection, $brokenSettings, self::$time);
            $this->assertSame('Could not clean the peers list.', $result);
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
    }

}
