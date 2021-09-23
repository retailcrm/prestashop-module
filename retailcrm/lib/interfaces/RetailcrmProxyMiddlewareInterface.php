<?php

interface RetailcrmProxyMiddlewareInterface
{
    public function __invoke(RetailcrmApiRequest $request, Closure $next = null);
}