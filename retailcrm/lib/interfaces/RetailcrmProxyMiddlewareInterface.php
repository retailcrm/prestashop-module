<?php

interface RetailcrmProxyMiddlewareInterface
{
    public function setNext($handler);

    public function handle($request = null);
}