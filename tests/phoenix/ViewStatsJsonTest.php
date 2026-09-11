<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewStatsJsonTest extends PhoenixTestCase
{
    /** A representative set of figures, reused so each test says only what it checks. */
    private const SAMPLE = [
        'peers' => 42,
        'seeders' => 30,
        'leechers' => 12,
        'torrents' => 5,
        'downloads' => 150,
        'traffic' => 1073741824, // 1 GB
    ];

    private const ZEROES = [
        'peers' => 0,
        'seeders' => 0,
        'leechers' => 0,
        'torrents' => 0,
        'downloads' => 0,
        'traffic' => 0,
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/json.stats.php';
    }

    /**
     * Render, decode, and return the 'tracker' object every response wraps.
     *
     * @param array<string, int> $stats
     * @return array<string, mixed>
     */
    private function tracker(array $stats): array
    {
        $decoded = json_decode(view_stats_json($stats, self::$settings), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tracker', $decoded);
        $this->assertIsArray($decoded['tracker']);

        return $decoded['tracker'];
    }

    public function testReturnsValidJson()
    {
        $this->assertJson(view_stats_json(self::SAMPLE, self::$settings), 'Output should be valid JSON');
    }

    public function testIncludesTrackerObject()
    {
        // tracker() asserts the wrapper itself.
        $this->assertNotSame([], $this->tracker(self::SAMPLE));
    }

    public function testIncludesAllStatFields()
    {
        $tracker = $this->tracker(self::SAMPLE);

        foreach (['version', 'peers', 'seeders', 'leechers', 'torrents', 'downloads', 'traffic'] as $field) {
            $this->assertArrayHasKey($field, $tracker);
        }
    }

    public function testCorrectStatValues()
    {
        $tracker = $this->tracker(self::SAMPLE);

        foreach (self::SAMPLE as $field => $expected) {
            $this->assertEquals($expected, $tracker[$field], $field);
        }
    }

    public function testVersionIsTheBareVersionString()
    {
        // Exactly the version, nothing around it: the '$Id: … $,' wrapper this
        // carried until v4.3 was a Subversion keyword plus a stray delimiter.
        $this->assertSame(
            self::$settings['phoenix_version'],
            $this->tracker(self::ZEROES)['version'],
        );
    }

    public function testHandlesZeroStats()
    {
        $tracker = $this->tracker(self::ZEROES);

        foreach (self::ZEROES as $field => $expected) {
            $this->assertEquals($expected, $tracker[$field], $field);
        }
    }
}
