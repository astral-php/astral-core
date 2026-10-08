<?php

declare(strict_types=1);

namespace Tests\Core\Http;

use Core\Http\JsonResponse;
use Core\Http\RedirectResponse;
use Core\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires des objets de réponse HTTP.
 * Couvre : Response, JsonResponse, RedirectResponse.
 * Aucun appel à send() (évite l'envoi d'en-têtes en CLI).
 */
final class ResponseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Response — constructeur & fabriques
    // -------------------------------------------------------------------------

    public function testDefaultResponseHasStatus200(): void
    {
        $response = new Response();
        $this->assertSame(200, $response->getStatus());
    }

    public function testDefaultResponseHasEmptyContent(): void
    {
        $response = new Response();
        $this->assertSame('', $response->getContent());
    }

    public function testHtmlFactoryCreatesResponseWithContent(): void
    {
        $response = Response::html('<h1>Hello</h1>');
        $this->assertSame('<h1>Hello</h1>', $response->getContent());
        $this->assertSame(200, $response->getStatus());
    }

    public function testHtmlFactoryAcceptsCustomStatusCode(): void
    {
        $response = Response::html('Created', 201);
        $this->assertSame(201, $response->getStatus());
    }

    // -------------------------------------------------------------------------
    // Response — fluent setters
    // -------------------------------------------------------------------------

    public function testSetContentUpdatesContent(): void
    {
        $response = new Response();
        $response->setContent('New content');
        $this->assertSame('New content', $response->getContent());
    }

    public function testSetStatusUpdatesStatusCode(): void
    {
        $response = new Response();
        $response->setStatus(404);
        $this->assertSame(404, $response->getStatus());
    }

    public function testAddHeaderStoresHeader(): void
    {
        $response = new Response();
        $response->addHeader('X-Custom', 'value');
        $this->assertSame(['X-Custom' => 'value'], $response->getHeaders());
    }

    public function testFluentChainingWorks(): void
    {
        $response = (new Response())
            ->setContent('body')
            ->setStatus(422)
            ->addHeader('X-Error', 'true');

        $this->assertSame('body', $response->getContent());
        $this->assertSame(422, $response->getStatus());
        $this->assertArrayHasKey('X-Error', $response->getHeaders());
    }

    public function testAddHeaderOverwritesPreviousValue(): void
    {
        $response = new Response();
        $response->addHeader('X-Foo', 'first');
        $response->addHeader('X-Foo', 'second');
        $this->assertSame('second', $response->getHeaders()['X-Foo']);
    }

    public function testGetHeadersReturnsEmptyArrayByDefault(): void
    {
        $response = new Response();
        $this->assertSame([], $response->getHeaders());
    }

    // -------------------------------------------------------------------------
    // JsonResponse
    // -------------------------------------------------------------------------

    public function testJsonResponseEncodesDataCorrectly(): void
    {
        $response = JsonResponse::make(['id' => 1, 'name' => 'Alice']);
        $decoded  = json_decode($response->getContent(), true);

        $this->assertSame(1, $decoded['id']);
        $this->assertSame('Alice', $decoded['name']);
    }

    public function testJsonResponseDefaultStatusIs200(): void
    {
        $response = JsonResponse::make([]);
        $this->assertSame(200, $response->getStatus());
    }

    public function testJsonResponseAcceptsCustomStatusCode(): void
    {
        $response = JsonResponse::make(['error' => 'Not found'], 404);
        $this->assertSame(404, $response->getStatus());
    }

    public function testJsonResponseSetsContentTypeHeader(): void
    {
        $response = JsonResponse::make([]);
        $headers  = $response->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertStringContainsString('application/json', $headers['Content-Type']);
    }

    public function testJsonResponseStatusIs422ForValidationErrors(): void
    {
        $response = JsonResponse::make(['errors' => ['name' => 'required']], 422);
        $this->assertSame(422, $response->getStatus());
    }

    public function testJsonResponseHandlesUnicode(): void
    {
        $response = JsonResponse::make(['message' => 'Données créées']);
        $this->assertStringContainsString('Données créées', $response->getContent());
    }

    public function testJsonResponseIsInstanceOfResponse(): void
    {
        $response = JsonResponse::make([]);
        $this->assertInstanceOf(Response::class, $response);
    }

    // -------------------------------------------------------------------------
    // RedirectResponse
    // -------------------------------------------------------------------------

    public function testRedirectResponseSetsLocationHeader(): void
    {
        $response = RedirectResponse::to('/dashboard');
        $headers  = $response->getHeaders();

        $this->assertArrayHasKey('Location', $headers);
        $this->assertSame('/dashboard', $headers['Location']);
    }

    public function testRedirectResponseDefaultStatusIs302(): void
    {
        $response = RedirectResponse::to('/login');
        $this->assertSame(302, $response->getStatus());
    }

    public function testRedirectResponseAcceptsCustomStatusCode(): void
    {
        $response = RedirectResponse::to('/new-page', 301);
        $this->assertSame(301, $response->getStatus());
    }

    public function testRedirectResponseHasEmptyContent(): void
    {
        $response = RedirectResponse::to('/somewhere');
        $this->assertSame('', $response->getContent());
    }

    public function testRedirectResponseIsInstanceOfResponse(): void
    {
        $response = RedirectResponse::to('/');
        $this->assertInstanceOf(Response::class, $response);
    }
}
