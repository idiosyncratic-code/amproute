<?php

declare(strict_types=1);

namespace Idiosyncratic\AmpRoute;

use PHPUnit\Framework\TestCase;

use function count;

class RouteGroupTest extends TestCase
{
    public function testCreateRouteGroup() : void
    {
        $handler = new TestRequestHandlerObserver();
        $middleware1 = new TestMiddleware('x-foo', 'bar');
        $middleware2 = new TestMiddleware('x-bar', 'baz');
        $middleware3 = new TestMiddlewareObserver('x-baz', 'foo');

        $routeGroup = new RouteGroup(
            '/hello',
            static function (RouteGroup $group) use ($handler, $middleware2, $middleware3): void {
                $group->map(
                    'GET',
                    '/world',
                    $handler,
                    $middleware2,
                );

                $group->map(
                    'GET',
                    '/universe',
                    $handler,
                    $middleware2,
                    $middleware3,
                );
            },
            $middleware1
        );

        $routes = $routeGroup->getRoutes();

        $this->assertEquals(2, count($routes));

        $this->assertEquals('/hello/world', $routes[0]->getPath());

        $this->assertEquals(2, count($routes[0]->getMiddleware()));

        $this->assertEquals('/hello/universe', $routes[1]->getPath());

        $this->assertEquals(3, count($routes[1]->getMiddleware()));
    }
}
