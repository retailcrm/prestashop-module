<?php

interface RetailcrmProxyMiddlewareInterface
{
    public function __invoke(RetailcrmApiRequest $request, RetailcrmApiResponse $response);
}