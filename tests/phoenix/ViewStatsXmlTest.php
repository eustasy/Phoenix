<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewStatsXmlTest extends TestCase
{
    public function testRenderXml()
    {
        require_once __DIR__.'/../../src/views/xml.stats.php';

        $stats = [
            'peers' => 15,
            'seeders' => 10,
            'leechers' => 5,
            'torrents' => 3,
            'downloads' => 100,
            'bandwidth' => 5000000,
        ];
        $settings = ['phoenix_version' => 'v1.0', 'phoenix_release' => 'Testing'];

        $output = view_stats_xml($stats, $settings);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', $output);
        // The bare version, as /api reports it — the '$Id: … $' wrapper was a
        // Subversion keyword this project has not been able to expand since it
        // left SVN.
        $this->assertStringContainsString('<tracker version="v1.0" release="Testing">', $output);
        $this->assertStringNotContainsString('$Id', $output);
        $this->assertStringContainsString('<peers>15</peers>', $output);
        $this->assertStringContainsString('<seeders>10</seeders>', $output);
        $this->assertStringContainsString('<leechers>5</leechers>', $output);
        $this->assertStringContainsString('<torrents>3</torrents>', $output);
        $this->assertStringContainsString('<downloads>100</downloads>', $output);
        $this->assertStringContainsString('<bandwidth>5000000</bandwidth>', $output);
        $this->assertStringContainsString('</tracker>', $output);
    }

}
