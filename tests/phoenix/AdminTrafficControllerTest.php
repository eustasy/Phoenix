<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.traffic.php';

class AdminTrafficControllerTest extends PhoenixTestCase
{
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` LIKE \'__TEST_%\' OR `info_hash` = \''.self::HASH.'\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\' OR `info_hash` = \''.self::HASH.'\';');
        parent::tearDown();
    }

    private function torrent(string $hash, string $name, int $size = 1000, int $downloads = 3): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES (?, ?, ?, 1, ?);',
            [$hash, $name, $size, $downloads],
        );
    }

    public function testDefaultsToActivePeersOnTheNinetyDayWindow(): void
    {
        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('Traffic', $html);
        $this->assertStringContainsString('metric=peers', $html);
        $this->assertStringContainsString('days=90', $html);
    }

    public function testEventsMetricIsSelectable(): void
    {
        $_GET['metric'] = 'events';

        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('metric=events', $html);
    }

    public function testUnknownMetricAndWindowFallBackToTheDefaults(): void
    {
        // Neither value may reach a model unchecked.
        $_GET['metric'] = 'nonsense';
        $_GET['days'] = '9999';

        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('metric=peers', $html);
        $this->assertStringContainsString('days=90', $html);
    }

    public function testKnownWindowIsHonoured(): void
    {
        $_GET['days'] = '30';

        $this->assertStringContainsString('days=30', \admin_traffic_controller(self::$connection, self::$settings));
    }

    public function testSearchNarrowsTheListing(): void
    {
        $this->torrent('__TEST_atc_a__', '__TEST_Findable__');
        $this->torrent('__TEST_atc_b__', '__TEST_Hidden__');
        $_GET['q'] = '__TEST_Findable__';

        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('__TEST_Findable__', $html);
        $this->assertStringNotContainsString('__TEST_Hidden__', $html);
    }

    public function testInfoHashNarrowsToOneTorrent(): void
    {
        $this->torrent(self::HASH, '__TEST_Only__');
        $this->torrent('__TEST_atc_b__', '__TEST_Other__');
        $_GET['info_hash'] = self::HASH;

        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('__TEST_Only__', $html);
        $this->assertStringNotContainsString('__TEST_Other__', $html);
    }

    public function testNegativeOffsetIsFlooredToZero(): void
    {
        $this->torrent('__TEST_atc_a__', '__TEST_Findable__');
        $_GET['q'] = '__TEST_Findable__';
        $_GET['offset'] = '-50';

        $html = \admin_traffic_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('__TEST_Findable__', $html);
        $this->assertStringContainsString('Showing 1', $html);
    }
}
