<?php

interface RetailcrmProxyMiddlewareInterface
{
    /**
     * @param RetailcrmApiRequest|null $request
     * @param RetailcrmApiResponse|null $response
     * @param RetailcrmProxyMiddlewareInterface|null $next
     * @return RetailcrmApiResponse
     */
    public function handle(
        RetailcrmApiRequest $request = null,
        RetailcrmApiResponse $response = null,
        RetailcrmProxyMiddlewareInterface $next = null
    );

    public function setNext(RetailcrmAbstractProxyMiddleware $nextMiddleware);
}