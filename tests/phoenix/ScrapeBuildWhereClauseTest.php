<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ScrapeBuildWhereClauseTest extends TestCase {

	public function testBuildWhereClauseWithSingleHash() {
		require_once __DIR__.'/../../src/functions/function.scrape.build.where.clause.php';

		$info_hashes = array(str_repeat('a', 40));

		$result = scrape_build_where_clause($info_hashes);

		$expected = 'WHERE  `p`.`info_hash`=\''.str_repeat('a', 40).'\'';
		$this->assertSame($expected, $result);
	}

	public function testBuildWhereClauseWithMultipleHashes() {
		require_once __DIR__.'/../../src/functions/function.scrape.build.where.clause.php';

		$info_hashes = array(
			str_repeat('a', 40),
			str_repeat('b', 40),
			str_repeat('c', 40),
		);

		$result = scrape_build_where_clause($info_hashes);

		$expected = 'WHERE  `p`.`info_hash`=\''.str_repeat('a', 40).'\''.
					' OR `p`.`info_hash`=\''.str_repeat('b', 40).'\''.
					' OR `p`.`info_hash`=\''.str_repeat('c', 40).'\'';
		$this->assertSame($expected, $result);
	}

	public function testBuildWhereClauseWithEmptyArray() {
		require_once __DIR__.'/../../src/functions/function.scrape.build.where.clause.php';

		$info_hashes = array();

		$result = scrape_build_where_clause($info_hashes);

		$this->assertSame('WHERE ', $result);
	}

}
