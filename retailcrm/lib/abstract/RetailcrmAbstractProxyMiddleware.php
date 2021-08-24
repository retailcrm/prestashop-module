<?php

class RetailcrmAbstractProxyMiddleware implements RetailcrmProxyMiddlewareInterface
{
    protected $nextMiddleware;

    public function process(RetailcrmApiRequest $request, RetailcrmApiResponse $response)
    {
        return $response;
    }

    public function getNext()
    {
        return $this->nextMiddleware;
    }

    public function setNext(RetailcrmAbstractProxyMiddleware $nextMiddleware)
    {
        $this->nextMiddleware = $nextMiddleware;
        return $this;
    }


}