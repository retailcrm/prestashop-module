<?php

class RetailcrmAbstractProxyMiddleware implements RetailcrmProxyMiddlewareInterface
{
    public function __invoke(RetailcrmApiRequest $request, Closure $next = null)
    {
        return $this($request, $next);
    }

}