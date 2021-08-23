<?php

class RetailcrmAbstractProxyMiddleware implements RetailcrmProxyMiddlewareInterface
{
    protected $nextMiddleware;

    public function handle(
        RetailcrmApiRequest $request = null,
        RetailcrmApiResponse $response = null,
        RetailcrmProxyMiddlewareInterface $next = null
    )
    {
        return $response;
    }

    public function getNext()
    {
        return $this->nextMiddleware;
    }

    public function setNext(RetailcrmAbstractProxyMiddleware $nextMiddleware = null)
    {
        $this->nextMiddleware = $nextMiddleware;
        return $this;
    }


}