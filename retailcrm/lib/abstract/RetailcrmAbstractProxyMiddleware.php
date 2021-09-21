<?php

class RetailcrmAbstractProxyMiddleware implements RetailcrmProxyMiddlewareInterface
{
    public function __invoke(RetailcrmApiRequest $request, RetailcrmApiResponse $response)
    {
        return $response;
    }

}