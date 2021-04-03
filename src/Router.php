<?php

declare(strict_types=1);

namespace Idiosyncratic\AmpRoute;

use Amp\Failure;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\ServerObserver;
use Amp\Promise;
use Amp\Success;
use Error;
use Psr\Log\LoggerInterface;
use SplObjectStorage;

final class Router implements RequestHandler, ServerObserver
{
    private Dispatcher $dispatcher;

    private RouteGroup $routes;

    /** @var SplObjectStorage<ServerObserver, null> */
    private SplObjectStorage $observers;

    private HttpServer $server;

    private bool $running = false;

    public function __construct(
        Dispatcher $dispatcher,
        RouteGroup $routes,
    ) {
        $this->dispatcher = $dispatcher;

        $this->routes = $routes;

        $this->observers = new SplObjectStorage();
    }

    public function handleRequest(Request $request) : Promise
    {
        return $this->dispatcher->dispatch($request)
               ->handleRequest($request);
    }

    /**
     * @return Promise<mixed>
     */
    public function onStart(HttpServer $server) : Promise
    {
        if ($this->running) {
            return new Failure(new Error('Router already started'));
        }

        $this->server = $server;

        $this->getLogger()->debug('Starting Router');

        $this->running = true;

        $routes = $this->routes->getRoutes();

        $this->dispatcher->setRoutes($routes);

        array_walk(
            $routes,
            function ($route) : void {
                foreach ($route->getServerObservers() as $observer) {
                    $this->observers->attach($observer);
                }
            },
        );

        if ($this->dispatcher instanceof ServerObserver) {
            $this->observers->attach($this->dispatcher);
        }

        $promises = [];

        foreach ($this->observers as $observer) {
            $promises[] = $observer->onStart($server);
        }

        /** @phpstan-ignore-next-line */
        return Promise\all($promises);
    }

    /**
     * @return Promise<mixed>
     */
    public function onStop(HttpServer $server) : Promise
    {
        $this->running = false;

        $promises = [];

        foreach ($this->observers as $observer) {
            $promises[] = $observer->onStop($server);
        }

        /** @phpstan-ignore-next-line */
        $this->observers->removeAll($this->observers);

        /** @phpstan-ignore-next-line */
        return Promise\all($promises);
    }

    private function getLogger() : LoggerInterface
    {
        return $this->server->getLogger();
    }
}
