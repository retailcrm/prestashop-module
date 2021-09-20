<?php

class RetailcrmProxyBuilder
{
    /** @var RetailcrmProxyMiddlewareInterface */
    protected $tip;

    public function registerMiddlewares($middlewares)
    {
        $middlewares = array_reverse($middlewares);
        foreach ($middlewares as $middlewareClass) {
            $this->addMiddleware($middlewareClass);
        }
    }

    public function addMiddleware($middlewareClass)
    {
        $middleware = new $middlewareClass;

        if (is_null($this->tip)) {
            $this->tip = $middleware;
            return $this;
        }
        $middleware->setNext($this->tip);
        $this->tip = $middleware;

        return $this;
    }

    public function run(RetailcrmApiRequest $request, RetailcrmApiResponse $response)
    {
        return $this->tip->process($request, $response);
    }

}