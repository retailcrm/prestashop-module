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
        if ($request['method']) {
            $loggerHandler = new RetailcrmLogMiddleware;
            $this->setNext($loggerHandler);

            if (isset($request['data']['id'])) {
                $response = $this->client->{$request['method']}($request['data']['id']);
                return $response;
            } elseif (isset($request['data'])){
                $response = $this->client->{$request['method']}($request['data']);
                return $response;
            }
        }
        return parent::handle($request);
    }
}