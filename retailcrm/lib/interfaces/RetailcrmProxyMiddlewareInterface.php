<?php

interface RetailcrmProxyMiddlewareInterface
{
    /**
     * @param RetailcrmApiRequest|null $request
     * @param RetailcrmApiResponse|null $response
     * @return RetailcrmApiResponse
     */
    public function process(RetailcrmApiRequest $request, RetailcrmApiResponse $response);

    public function setNext(RetailcrmAbstractProxyMiddleware $nextMiddleware);
}