<?php

declare(strict_types=1);

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

		// An empty hash list must not produce a bare 'WHERE ' — that would be
		// concatenated onto the model's SELECT and cause a syntax error.
		$this->assertSame('', scrape_build_where_clause(array()));
	}

}
