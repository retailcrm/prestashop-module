<?php

class RetailcrmProxyBuilder
{
    /** @var RetailcrmProxyMiddlewareInterface */
    protected $tip;

    public function addMiddleware($middleware)
    {

        $middleware->setNext($this->tip);
        $this->tip = $middleware;

        return $this;
    }

    public function run(RetailcrmApiRequest $request)
    {
        return $this->tip->handle($request, null, $this->tip->getNext());
    }

}