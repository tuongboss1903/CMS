<?php

declare(strict_types=1);

namespace Tests\Core\Http;

use Core\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testWithRouteParamsReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $original = new Request('GET', '/users/5', 'example.com');

        $withParams = $original->withRouteParams(['id' => '5']);

        self::assertNotSame($original, $withParams);
        self::assertNull($original->routeParam('id'));
        self::assertSame('5', $withParams->routeParam('id'));
    }

    public function testQueryAndInputReadFromConstructorData(): void
    {
        $request = new Request('GET', '/search', 'example.com', query: ['q' => 'cms'], body: ['name' => 'A']);

        self::assertSame('cms', $request->query('q'));
        self::assertSame('A', $request->input('name'));
        self::assertNull($request->query('missing'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', '/', 'example.com', headers: ['X-CUSTOM' => 'value']);

        self::assertSame('value', $request->header('x-custom'));
        self::assertSame('value', $request->header('X-Custom'));
    }
}
