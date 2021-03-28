<?php

declare(strict_types=1);

namespace Idiosyncratic\Amp\Http\Server\Router;

interface Dispatcher
{
    public function dispatch(string $method, string $path) : DispatchResult;

    /**
     * @param array<mixed> $routes
     */
    public function compile(array $routes) : void;

    public function compiled() : bool;
}
