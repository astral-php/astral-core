<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\ErrorHandler;
use Core\Exception\AuthorizationException;
use Core\Exception\CsrfException;
use Core\Exception\NotFoundException;
use Core\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    private string $logDir;
    private Logger $logger;

    protected function setUp(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
        }

        $this->logDir = sys_get_temp_dir() . '/astral_error_handler_' . uniqid('', true);
        mkdir($this->logDir, 0755, true);
        $this->logger = new Logger($this->logDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    public function testNotFoundRenders404WithoutLogging(): void
    {
        $handler = new ErrorHandler($this->logger, debug: false);

        ob_start();
        $handler->handleException(new NotFoundException('Page absente'));
        $output = ob_get_clean() ?: '';

        // Sans vues app (BASE_PATH package) → fallback HTML (titre uniquement si debug=false)
        $this->assertStringContainsString('404', $output);
        $this->assertStringContainsString('Page introuvable', $output);
        $this->assertSame([], glob($this->logDir . '/*.log') ?: []);
    }

    public function testAuthorizationRenders403(): void
    {
        $handler = new ErrorHandler($this->logger, debug: false);

        ob_start();
        $handler->handleException(new AuthorizationException('Interdit'));
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('403', $output);
        $this->assertStringContainsString('Accès refusé', $output);
    }

    public function testCsrfRenders403AndLogsWarning(): void
    {
        $handler = new ErrorHandler($this->logger, debug: false);

        ob_start();
        $handler->handleException(new CsrfException('Token invalide'));
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('403', $output);
        $logs = glob($this->logDir . '/*.log') ?: [];
        $this->assertNotEmpty($logs);
        $content = file_get_contents($logs[0]) ?: '';
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('CSRF', $content);
    }

    public function testGenericExceptionLogsErrorAndRenders500(): void
    {
        $handler = new ErrorHandler($this->logger, debug: false);

        ob_start();
        $handler->handleException(new RuntimeException('Boom'));
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('500', $output);
        $this->assertStringNotContainsString('Boom', $output);

        $logs = glob($this->logDir . '/*.log') ?: [];
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('ERROR', file_get_contents($logs[0]) ?: '');
    }

    public function testDebugModeExposesExceptionDetails(): void
    {
        $handler = new ErrorHandler($this->logger, debug: true);

        ob_start();
        $handler->handleException(new RuntimeException('Secret details'));
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('Secret details', $output);
        $this->assertStringContainsString(RuntimeException::class, $output);
    }

    public function testHandleErrorThrowsErrorException(): void
    {
        $handler = new ErrorHandler($this->logger, debug: true);
        $previous = error_reporting(E_ALL);

        try {
            $this->expectException(\ErrorException::class);
            $handler->handleError(E_WARNING, 'Attention', __FILE__, __LINE__);
        } finally {
            error_reporting($previous);
        }
    }
}
