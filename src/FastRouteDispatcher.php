<?php

declare(strict_types=1);

namespace Idiosyncratic\Amp\Http\Server\Router;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\RequestHandler;
use Error;
use FastRoute\Dispatcher as FastRoute;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;

use function array_map;
use function count;
use function FastRoute\simpleDispatcher;
use function is_string;
use function sprintf;

final class FastRouteDispatcher implements Dispatcher
{
    private ContainerInterface $container;

    private FastRoute $dispatcher;

    private bool $compiled = false;

    public function __construct(
        ContainerInterface $container,
    ) {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function dispatch(string $method, string $path) : array
    {
        $dispatched = $this->dispatcher->dispatch($method, $path);

        if ($dispatched[0] === FastRoute::METHOD_NOT_ALLOWED) {
            return [
                'status' => $dispatched[0],
                'allowedMethods' => $dispatched[1],
            ];
        }

        if ($dispatched[0] === FastRoute::NOT_FOUND) {
            return [
                'status' => $dispatched[0],
            ];
        }

        $requestHandler = is_string($dispatched[1]['handler']) ?
            $this->container->get($dispatched[1]['handler']) :
            $dispatched[1]['handler'];

        if (count($dispatched[1]['middleware']) > 0) {
            $requestHandler = $this->makeMiddlewareRequestHandler($requestHandler, $dispatched[1]['middleware']);
        }

        return [
            'status' => FastRoute::FOUND,
            'handler' => $requestHandler,
            'routeArgs' => $dispatched[2],
        ];
    }

    /**
     * @inheritdoc
     */
    public function compile(array $routes) : void
    {
        if ($this->compiled === true) {
            throw new Error('Routes already compiled');
        }

        $this->dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($routes) : void {
            foreach ($routes as [$method, $uri, $requestHandler]) {
                $uri = sprintf('/%s', $uri);

                $collector->addRoute($method, $uri, $requestHandler);
            }
        });

        $this->compiled = true;
    }

    public function compiled() : bool
    {
        return $this->compiled;
    }

    /**
     * @param array<string | Middleware> $middleware
     */
    private function makeMiddlewareRequestHandler(
        RequestHandler $requestHandler,
        array $middleware,
    ) : RequestHandler {
        $middleware = array_map(function ($item) {
            return $item instanceof Middleware ?
                $item :
                $this->container->get($item);
        }, $middleware);

        return new MiddlewareRequestHandler($requestHandler, ...$middleware);
    }
}