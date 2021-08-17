<?php

class RetailcrmAbstractProxyMiddleware implements RetailcrmProxyMiddlewareInterface
{
    private $nextHandler;

    public function setNext($handler)
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle($request = null)
    {
        if ($this->nextHandler) {
            return $this->nextHandler->handle($request);
        }

        return null;
    }
}