<?php

class RetailcrmProxyBuilder
{
    private $middlewares;

    public function registerMiddlewares($middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function run(RetailcrmApiRequest $request, RetailcrmApiResponse $response)
    {
        foreach ($this->middlewares as $middlewareClass) {
            $middleware = new $middlewareClass();
            $response = $middleware($request, $response);
        }
        return $response;
    }

}