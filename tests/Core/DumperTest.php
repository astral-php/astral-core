<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Dumper\Dumper;
use PHPUnit\Framework\TestCase;

final class DumperTest extends TestCase
{
    public function testDumpOutputsScalarInCli(): void
    {
        ob_start();
        Dumper::dump('hello', 42, true, null);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('42', $output);
        $this->assertStringContainsString('true', $output);
        $this->assertStringContainsString('null', $output);
    }

    public function testDumpHelperIsAvailable(): void
    {
        $this->assertTrue(function_exists('dump'));
        $this->assertTrue(function_exists('dd'));

        ob_start();
        dump(['a' => 1]);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('array', $output);
        $this->assertStringContainsString('1', $output);
    }
}
