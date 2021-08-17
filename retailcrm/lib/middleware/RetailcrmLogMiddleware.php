<?php

class RetailcrmLogMiddleware extends RetailcrmAbstractProxyMiddleware
{

    public function handle($request = null)
    {
        if (isset($request['id'])) {
            RetailcrmLogger::writeCaller($request['method'], print_r($request['id'], true));
        } elseif (isset($request['data'])) {
            RetailcrmLogger::writeCaller($request['method'], print_r(array_keys($request['data']), true));
        }
        return parent::handle($request);
    }
}