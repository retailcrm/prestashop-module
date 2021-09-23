<?php

class RetailcrmPipeline
{
    public $middlewares;

    public function run(RetailcrmApiRequest $request, callable $action)
    {
        $pipeline =
            array_reduce(
                array_reverse($this->middlewares),
                $this->addMiddleware(),
                $action
            );

        return $pipeline($request);
    }

    private function addMiddleware()
    {
        return function ($stack, $middlewareClass) {
            return function ($request) use ($stack, $middlewareClass) {
                $middleware = new $middlewareClass;
                return $middleware($request, $stack);
            };
        };
    }
}