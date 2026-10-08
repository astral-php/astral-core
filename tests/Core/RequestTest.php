<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de la classe Request.
 * Couvre : méthode HTTP, verb spoofing, URI, input/query, headers, isJson, isXhr.
 */
final class RequestTest extends TestCase
{
    private function makeRequest(
        string $method = 'GET',
        string $uri = '/',
        array  $post = [],
        array  $get = [],
        array  $server = []
    ): Request {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_POST                     = $post;
        $_GET                      = $get;

        foreach ($server as $k => $v) {
            $_SERVER[$k] = $v;
        }

        return new Request();
    }

    protected function tearDown(): void
    {
        // Nettoyage des superglobales après chaque test
        $_SERVER = array_filter($_SERVER, fn($k) => !in_array($k, [
            'REQUEST_METHOD', 'REQUEST_URI', 'CONTENT_TYPE',
            'HTTP_X_REQUESTED_WITH', 'HTTP_ACCEPT',
        ], true), ARRAY_FILTER_USE_KEY);
        $_POST = [];
        $_GET  = [];
    }

    // -------------------------------------------------------------------------
    // Méthode HTTP
    // -------------------------------------------------------------------------

    public function testGetMethodParsedCorrectly(): void
    {
        $request = $this->makeRequest('GET', '/users');
        $this->assertSame('GET', $request->method);
    }

    public function testPostMethodParsedCorrectly(): void
    {
        $request = $this->makeRequest('POST', '/users');
        $this->assertSame('POST', $request->method);
    }

    public function testIsMethodReturnsTrueForMatchingMethod(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertTrue($request->isMethod('GET'));
        $this->assertTrue($request->isMethod('get'));
    }

    public function testIsMethodReturnsFalseForOtherMethod(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertFalse($request->isMethod('POST'));
    }

    // -------------------------------------------------------------------------
    // Verb spoofing (_method)
    // -------------------------------------------------------------------------

    public function testVerbSpoofingPutFromPost(): void
    {
        $request = $this->makeRequest('POST', '/users/1', ['_method' => 'PUT']);
        $this->assertSame('PUT', $request->method);
    }

    public function testVerbSpoofingPatchFromPost(): void
    {
        $request = $this->makeRequest('POST', '/users/1', ['_method' => 'PATCH']);
        $this->assertSame('PATCH', $request->method);
    }

    public function testVerbSpoofingDeleteFromPost(): void
    {
        $request = $this->makeRequest('POST', '/users/1', ['_method' => 'DELETE']);
        $this->assertSame('DELETE', $request->method);
    }

    public function testVerbSpoofingIgnoredForNonPost(): void
    {
        $request = $this->makeRequest('GET', '/users/1', ['_method' => 'DELETE']);
        $this->assertSame('GET', $request->method);
    }

    public function testVerbSpoofingIgnoresInvalidValue(): void
    {
        $request = $this->makeRequest('POST', '/users', ['_method' => 'CONNECT']);
        $this->assertSame('POST', $request->method);
    }

    // -------------------------------------------------------------------------
    // URI
    // -------------------------------------------------------------------------

    public function testUriParsedWithoutQueryString(): void
    {
        $request = $this->makeRequest('GET', '/users?page=2');
        $this->assertSame('/users', $request->uri);
    }

    public function testUriTrailingSlashStripped(): void
    {
        $request = $this->makeRequest('GET', '/users/');
        $this->assertSame('/users', $request->uri);
    }

    public function testRootUriPreserved(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertSame('/', $request->uri);
    }

    // -------------------------------------------------------------------------
    // input() / post()
    // -------------------------------------------------------------------------

    public function testInputReturnsPostValue(): void
    {
        $request = $this->makeRequest('POST', '/users', ['name' => 'Alice']);
        $this->assertSame('Alice', $request->input('name'));
    }

    public function testInputReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('POST', '/users', []);
        $this->assertSame('default', $request->input('name', 'default'));
        $this->assertNull($request->input('name'));
    }

    public function testPostWithKeyReturnsValue(): void
    {
        $request = $this->makeRequest('POST', '/users', ['email' => 'a@b.com']);
        $this->assertSame('a@b.com', $request->post('email'));
    }

    public function testPostWithoutKeyReturnsFullBody(): void
    {
        $data    = ['name' => 'Alice', 'email' => 'a@b.com'];
        $request = $this->makeRequest('POST', '/users', $data);
        $this->assertSame($data, $request->post());
    }

    // -------------------------------------------------------------------------
    // query()
    // -------------------------------------------------------------------------

    public function testQueryReturnsGetParam(): void
    {
        $request = $this->makeRequest('GET', '/users?page=3', [], ['page' => '3']);
        $this->assertSame('3', $request->query('page'));
    }

    public function testQueryReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('GET', '/users', [], []);
        $this->assertSame(1, $request->query('page', 1));
    }

    // -------------------------------------------------------------------------
    // header()
    // -------------------------------------------------------------------------

    public function testHeaderReturnsValue(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame('application/json', $request->header('Accept'));
    }

    public function testHeaderReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertNull($request->header('X-Custom'));
        $this->assertSame('fallback', $request->header('X-Custom', 'fallback'));
    }

    // -------------------------------------------------------------------------
    // isJson() / isXhr()
    // -------------------------------------------------------------------------

    public function testIsJsonReturnsTrueForJsonContentType(): void
    {
        $request = $this->makeRequest('POST', '/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);
        $this->assertTrue($request->isJson());
    }

    public function testIsJsonReturnsFalseForFormContentType(): void
    {
        $request = $this->makeRequest('POST', '/', [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);
        $this->assertFalse($request->isJson());
    }

    public function testIsXhrReturnsTrueForXmlHttpRequest(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $this->assertTrue($request->isXhr());
    }

    public function testIsXhrReturnsFalseForRegularRequest(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertFalse($request->isXhr());
    }
}
