<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsScrapeTest extends PhoenixTestCase
{
    public function testQueryTorrentsWithSingleHash()
    {
        require_once __DIR__.'/../../src/model/torrents.scrape.php';
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        $info_hash = str_repeat('a', 40);

        // Insert test torrent
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
               '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
               "('".$info_hash."', '__TEST_a', 1000, 5);";
        mysqli_query(self::$connection, $sql);

        $where = scrape_build_where_clause([$info_hash]);
        $result = torrents_scrape(self::$connection, self::$settings, $where);

        $this->assertNotFalse($result);
        $row = mysqli_fetch_assoc($result);
        $this->assertSame($info_hash, $row['info_hash']);
        $this->assertSame('1000', $row['size']);
        $this->assertSame('5', $row['downloads']);
    }

    public function testQueryTorrentsWithMultipleHashes()
    {
        require_once __DIR__.'/../../src/model/torrents.scrape.php';
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        $info_hash_a = str_repeat('a', 40);
        $info_hash_b = str_repeat('b', 40);

        // Insert test torrents
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
               '(`info_hash`, `name`, `size`, `downloads`) VALUES '.
               "('".$info_hash_a."', '__TEST_a', 1000, 5), ".
               "('".$info_hash_b."', '__TEST_b', 2000, 3);";
        mysqli_query(self::$connection, $sql);

        $where = scrape_build_where_clause([$info_hash_a, $info_hash_b]);
        $result = torrents_scrape(self::$connection, self::$settings, $where);

        $this->assertNotFalse($result);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[$row['info_hash']] = $row;
        }

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey($info_hash_a, $rows);
        $this->assertArrayHasKey($info_hash_b, $rows);
        $this->assertSame('1000', $rows[$info_hash_a]['size']);
        $this->assertSame('5', $rows[$info_hash_a]['downloads']);
        $this->assertSame('2000', $rows[$info_hash_b]['size']);
        $this->assertSame('3', $rows[$info_hash_b]['downloads']);
    }

    public function testQueryTorrentsReturnsEmptyForUnknownHash()
    {
        require_once __DIR__.'/../../src/model/torrents.scrape.php';
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        $info_hash = str_repeat('z', 40);
        $where = scrape_build_where_clause([$info_hash]);
        $result = torrents_scrape(self::$connection, self::$settings, $where);

        $this->assertNotFalse($result);
        $this->assertSame(0, mysqli_num_rows($result));
    }

    protected function tearDown(): void
    {
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `name` LIKE \'__TEST_%\'');
    }

}
