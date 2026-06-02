<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AnnounceCheckRateLimitTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/announce.check.rate.limit.php';
    }

    public function testNoRateLimitWhenNoPeersExist()
    {

        $peer = [
            'info_hash' => str_repeat('a', 40),
            'peer_id' => str_repeat('1', 40),
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        // Should not throw error
        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true); // If we get here, no error was thrown
    }

    public function testNoRateLimitWhenSamePeerIdExists()
    {
        $info_hash = str_repeat('a', 40);
        $peer_id = str_repeat('1', 40);

        // Insert existing peer with same peer_id
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash."', '".$peer_id."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => $peer_id,
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        // Should not throw error (same peer_id is excluded from query)
        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testNoRateLimitWhenPeerOutsideTimeWindow()
    {
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $peer_id_2 = str_repeat('2', 40);

        // Insert peer updated outside the time window (min_interval/5 ago)
        $threshold = self::$time - intval(self::$settings['min_interval'] / 5);
        $old_time = $threshold - 100; // Well outside the window

        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".$old_time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => $peer_id_2,
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        // Should not throw error (old peer is outside time window)
        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testRateLimitExceededWithDifferentPeerIdSameIpv4()
    {
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $peer_id_2 = str_repeat('2', 40);

        // Insert recent peer with different peer_id but same IPv4
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => $peer_id_2,
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        // Test in subprocess because tracker_error() calls exit()
        $this->assertRateLimitErrorInSubprocess($peer);
    }

    public function testRateLimitExceededWithDifferentPeerIdSameIpv6()
    {
        $info_hash = str_repeat('b', 40);
        $peer_id_1 = str_repeat('3', 40);
        $peer_id_2 = str_repeat('4', 40);

        // Insert recent peer with different peer_id but same IPv6
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash."', '".$peer_id_1."', '', 'fc00::1', '', '', 0, 6881, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => $peer_id_2,
            'ipv4' => null,
            'ipv6' => 'fc00::1',
        ];

        // Test in subprocess
        $this->assertRateLimitErrorInSubprocess($peer);
    }

    public function testRateLimitWithBothIpv4AndIpv6()
    {
        $info_hash = str_repeat('c', 40);
        $peer_id_1 = str_repeat('5', 40);
        $peer_id_2 = str_repeat('6', 40);

        // Insert peer with both IPv4 and IPv6
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash."', '".$peer_id_1."', '192.0.2.100', 'fc00::100', '', '', 6881, 6881, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => $peer_id_2,
            'ipv4' => '192.0.2.100',
            'ipv6' => 'fc00::100',
        ];

        // Test in subprocess
        $this->assertRateLimitErrorInSubprocess($peer);
    }

    public function testNoRateLimitForDifferentTorrent()
    {
        $info_hash_1 = str_repeat('a', 40);
        $info_hash_2 = str_repeat('b', 40);
        $peer_id_1 = str_repeat('1', 40);
        $peer_id_2 = str_repeat('2', 40);

        // Insert peer for torrent 1
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
               '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
               "('".$info_hash_1."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash_2, // Different torrent
            'peer_id' => $peer_id_2,
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        // Should not throw error (different info_hash)
        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testNoRateLimitWhenAnnouncerHasNoIps()
    {
        // Insert a recent peer that would otherwise match.
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        // Announcer has neither family — peers_count_rate must short-circuit
        // to 0 instead of building a WHERE clause with no IP predicate.
        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => str_repeat('2', 40),
            'ipv4' => null,
            'ipv6' => null,
        ];

        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testEmptyStringIpsTreatedLikeNoIps()
    {
        // Same shape as the no-IPs case, but using '' (the schema default for
        // the missing family) instead of null. Both must be falsy so the
        // short-circuit fires.
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => str_repeat('2', 40),
            'ipv4' => '',
            'ipv6' => '',
        ];

        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testNoRateLimitWhenOnlyOtherFamilyMatches()
    {
        // Existing peer has IPv6 'fc00::1' and no IPv4. Announcer has IPv4
        // '192.0.2.1' and no IPv6 — the WHERE OR clause only includes
        // `ipv4`='192.0.2.1' (announcer has no ipv6 to add), so the existing
        // IPv6-only peer must not match.
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            "('".$info_hash."', '".$peer_id_1."', '', 'fc00::1', '', '', 0, 6881, '1', ".self::$time.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => str_repeat('2', 40),
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);
    }

    public function testThresholdBoundaryIsExclusive()
    {
        // `updated > threshold` is strict, so a peer updated exactly at the
        // threshold timestamp must NOT count, while threshold+1 must count.
        $info_hash = str_repeat('a', 40);
        $peer_id_1 = str_repeat('1', 40);
        $threshold = self::$time - intval(self::$settings['min_interval'] / 5);

        // Boundary: updated == threshold → excluded by '>' comparison.
        $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            "('".$info_hash."', '".$peer_id_1."', '192.0.2.1', '', '', '', 6881, 0, '1', ".$threshold.');';
        mysqli_query(self::$connection, $sql);

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => str_repeat('2', 40),
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        announce_check_rate_limit(self::$connection, self::$settings, $peer, self::$time);
        $this->assertTrue(true);

        // One second past threshold → must trigger.
        mysqli_query(
            self::$connection,
            'UPDATE `'.self::$settings['db_prefix'].'peers` SET `updated`='.($threshold + 1).
            ' WHERE `peer_id`=\''.$peer_id_1.'\';',
        );
        $this->assertRateLimitErrorInSubprocess($peer);
    }

    public function testMultipleOffendersStillTrigger()
    {
        // Three recent peers from the same IP, all with different peer_ids.
        // count > 0 already covers any positive count, but pin the behaviour.
        $info_hash = str_repeat('a', 40);
        for ($i = 1; $i <= 3; $i++) {
            $pid = str_repeat((string) $i, 40);
            $sql = 'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
                '(`info_hash`, `peer_id`, `ipv4`, `ipv6`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
                "('".$info_hash."', '".$pid."', '192.0.2.1', '', '', '', 6881, 0, '1', ".self::$time.');';
            mysqli_query(self::$connection, $sql);
        }

        $peer = [
            'info_hash' => $info_hash,
            'peer_id' => str_repeat('9', 40),
            'ipv4' => '192.0.2.1',
            'ipv6' => null,
        ];

        $this->assertRateLimitErrorInSubprocess($peer);
    }

    /**
     * Helper to test rate limit error in subprocess (since tracker_error calls exit).
     */
    private function assertRateLimitErrorInSubprocess($peer)
    {
        $functionPath = __DIR__.'/../../src/functions/announce.check.rate.limit.php';
        $script = '<?php '.
            '$settings = '.var_export(self::$settings, true).'; '.
            '$connection = mysqli_connect('.
            var_export(self::$settings['db_host'], true).', '.
            var_export(self::$settings['db_user'], true).', '.
            var_export(self::$settings['db_pass'], true).', '.
            var_export(self::$settings['db_name'], true).
            '); '.
            'require '.var_export(__DIR__.'/../../src/functions/tracker.error.php', true).'; '.
            'require '.var_export($functionPath, true).'; '.
            'announce_check_rate_limit($connection, $settings, '.
            var_export($peer, true).', '.var_export(self::$time, true).');';

        $result = $this->runPhpSubprocess($script);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Announce rate limit exceeded', $result['stdout']);
    }

    protected function tearDown(): void
    {
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `peer_id` LIKE \'__TEST_%\' OR `peer_id` REGEXP \'^[0-9]+$\'');
    }

}
