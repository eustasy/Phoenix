<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewIndexJsonTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/json.index.php';
    }

    /**
     * Minimal fixture row without meta keys — simulates a plain insert.
     * @return array<string, mixed>
     */
    private function baseRow(): array
    {
        return [
            'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'name' => 'Test Torrent',
            'size' => 1024,
            'downloads' => 7,
            'seeders' => 2,
            'leechers' => 1,
            'peers' => 3,
            'traffic' => 7168,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ];
    }

    /**
     * Fixture row with all meta populated.
     * @return array<string, mixed>
     */
    private function metaRow(): array
    {
        return array_merge($this->baseRow(), [
            'filename' => 'test.mkv',
            'files' => [['path' => 'test.mkv', 'length' => 1024]],
            'trackers' => ['https://tracker.example/announce'],
            'webseeds' => ['https://seed.example/test.mkv'],
        ]);
    }

    ////	output format

    public function testReturnsValidJson(): void
    {
        $result = \view_index_json([]);
        $this->assertJson($result);
    }

    public function testEmptyIndexEncodesAsEmptyArray(): void
    {
        $result = \view_index_json([]);
        $this->assertSame('[]', $result);
    }

    public function testSingleRowEncodesCorrectly(): void
    {
        $result = \view_index_json([$this->baseRow()]);
        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
    }

    ////	meta omitted by default

    public function testMetaOmittedByDefault(): void
    {
        $result = \view_index_json([$this->metaRow()]);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertArrayNotHasKey('filename', $row);
        $this->assertArrayNotHasKey('files', $row);
        $this->assertArrayNotHasKey('trackers', $row);
        $this->assertArrayNotHasKey('webseeds', $row);
    }

    public function testMetaOmittedWhenShowMetaFalse(): void
    {
        $result = \view_index_json([$this->metaRow()], false);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertArrayNotHasKey('filename', $row);
        $this->assertArrayNotHasKey('files', $row);
        $this->assertArrayNotHasKey('trackers', $row);
        $this->assertArrayNotHasKey('webseeds', $row);
    }

    public function testBaseFieldsPresentWhenMetaOmitted(): void
    {
        $result = \view_index_json([$this->baseRow()], false);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertArrayHasKey('info_hash', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('size', $row);
        $this->assertArrayHasKey('downloads', $row);
        $this->assertArrayHasKey('seeders', $row);
        $this->assertArrayHasKey('leechers', $row);
        $this->assertArrayHasKey('peers', $row);
        $this->assertArrayHasKey('traffic', $row);
    }

    ////	meta included with flag

    public function testMetaIncludedWhenShowMetaTrue(): void
    {
        $result = \view_index_json([$this->metaRow()], true);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertArrayHasKey('filename', $row);
        $this->assertArrayHasKey('files', $row);
        $this->assertArrayHasKey('trackers', $row);
        $this->assertArrayHasKey('webseeds', $row);
    }

    public function testMetaValuesPreservedWhenShowMetaTrue(): void
    {
        $result = \view_index_json([$this->metaRow()], true);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertSame('test.mkv', $row['filename']);
        $this->assertSame([['path' => 'test.mkv', 'length' => 1024]], $row['files']);
        $this->assertSame(['https://tracker.example/announce'], $row['trackers']);
        $this->assertSame(['https://seed.example/test.mkv'], $row['webseeds']);
    }

    public function testNullMetaValuesPassedThroughWhenShowMetaTrue(): void
    {
        // When meta columns are null and show_meta is true, null is included.
        $result = \view_index_json([$this->baseRow()], true);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $row = $decoded[0];

        $this->assertArrayHasKey('filename', $row);
        $this->assertNull($row['filename']);
        $this->assertNull($row['files']);
        $this->assertNull($row['trackers']);
        $this->assertNull($row['webseeds']);
    }

    ////	multiple rows

    public function testMultipleRowsEncoded(): void
    {
        $index = [$this->baseRow(), array_merge($this->baseRow(), [
            'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'name' => 'Second Torrent',
        ])];

        $result = \view_index_json($index);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('Second Torrent', $decoded[1]['name']);
    }

    ////	magnet

    public function testMagnetIncludedWithoutMetaFlag(): void
    {
        $magnet = 'magnet:?xt=urn:btih:'.str_repeat('a', 40);
        $row = array_merge($this->baseRow(), ['magnet' => $magnet]);

        $decoded = json_decode(\view_index_json([$row]), true);
        $this->assertIsArray($decoded);
        $this->assertSame($magnet, $decoded[0]['magnet']);
    }

    public function testMagnetNullWhenAbsentFromRow(): void
    {
        $decoded = json_decode(\view_index_json([$this->baseRow()]), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('magnet', $decoded[0]);
        $this->assertNull($decoded[0]['magnet']);
    }

    public function testMagnetPassedThroughWithMetaFlag(): void
    {
        $magnet = 'magnet:?xt=urn:btih:'.str_repeat('a', 40).'&dn=Test';
        $row = array_merge($this->metaRow(), ['magnet' => $magnet]);

        $decoded = json_decode(\view_index_json([$row], true), true);
        $this->assertIsArray($decoded);
        $this->assertSame($magnet, $decoded[0]['magnet']);
    }

    ////	valid JSON

    public function testOutputIsAlwaysValidJson(): void
    {
        foreach ([true, false] as $showMeta) {
            $result = \view_index_json([$this->metaRow()], $showMeta);
            $this->assertJson($result, 'Output must be valid JSON when show_meta='.($showMeta ? 'true' : 'false'));
        }
    }
}
