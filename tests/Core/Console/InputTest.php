<?php

declare(strict_types=1);

namespace Tests\Core\Console;

use Core\Console\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testParsesArgumentsAndLongOptions(): void
    {
        $input = new Input(['Article', '--api', '--port=8085', '--host', '127.0.0.1']);

        self::assertSame(['Article'], $input->arguments());
        self::assertTrue($input->hasOption('api'));
        self::assertSame('8085', $input->optionString('port'));
        self::assertSame('127.0.0.1', $input->optionString('host'));
    }

    public function testWantsHelp(): void
    {
        self::assertTrue((new Input(['--help']))->wantsHelp());
        self::assertTrue((new Input(['-h']))->wantsHelp());
        self::assertFalse((new Input(['Article']))->wantsHelp());
    }

    public function testUnknownOptions(): void
    {
        $input = new Input(['--api', '--foo']);
        self::assertSame(['foo'], $input->unknownOptions(['api', 'help', 'h']));
    }
}
