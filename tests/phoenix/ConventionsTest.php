<?php

declare(strict_types=1);

namespace Phoenix\Tests;

////	ConventionsTest
// Enforces the structural conventions from .github/CONTRIBUTING.md so they
// can't silently regress: every file in the four one-function-per-file
// directories defines exactly one top-level function, and hook files (which
// execute inside phoenix_hook()'s scope on every event) define none.
class ConventionsTest extends PhoenixTestCase
{
    private const ONE_FUNCTION_DIRS = [
        'src/functions',
        'src/model',
        'src/views',
        'src/controller',
    ];

    /**
     * Top-level function declarations only: the codebase declares functions
     * at column 0, so methods, closures, and arrow functions never match.
     */
    private function functionCount(string $path): int
    {
        $source = (string) file_get_contents($path);

        return preg_match_all('/^function\s+\w+/m', $source);
    }

    public function testOneFunctionPerFile(): void
    {
        $root = dirname(__DIR__, 2);
        $checked = 0;

        foreach (self::ONE_FUNCTION_DIRS as $dir) {
            foreach (glob($root.'/'.$dir.'/*.php') ?: [] as $file) {
                $count = $this->functionCount($file);
                $this->assertSame(
                    1,
                    $count,
                    $dir.'/'.basename($file).' defines '.$count.' top-level functions; '.
                    'the convention is exactly one per file (helpers get their own '.
                    '<category>.<verb>.php file — see .github/CONTRIBUTING.md).',
                );
                $checked++;
            }
        }

        // Guard the glob itself: if the layout moves, this test must fail
        // loudly rather than silently checking nothing.
        $this->assertGreaterThan(50, $checked);
    }

    public function testHookFilesDeclareNoFunctions(): void
    {
        $root = dirname(__DIR__, 2);
        $hooks = glob($root.'/src/hooks/*.php') ?: [];
        $this->assertNotEmpty($hooks);

        foreach ($hooks as $file) {
            $this->assertSame(
                0,
                $this->functionCount($file),
                'src/hooks/'.basename($file).' declares a top-level function; hooks are '.
                'include()d per event, so a declaration would fatal on the second firing.',
            );
        }
    }
}
