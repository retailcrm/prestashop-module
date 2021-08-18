<?php


class RetailcrmRequestMiddleware extends RetailcrmAbstractProxyMiddleware
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function handle($request = null)
    {
        if ($request->getMethod()) {
            return call_user_func_array([$this->client, $request->getMethod()], $request->getData());
        }
        return parent::handle($request);
    }
}