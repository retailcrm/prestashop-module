<?php


class RetailcrmRequestMiddleware extends RetailcrmAbstractProxyMiddleware
{
    const API_URL = 'RETAILCRM_ADDRESS';
    const API_KEY = 'RETAILCRM_API_TOKEN';

    protected $client;

    public function __construct()
    {
        $url = Configuration::get(static::API_URL);
        $key = Configuration::get(static::API_KEY);
        $this->client = new RetailcrmApiClientV5($url, $key);
    }

    public function handle(
        RetailcrmApiRequest $request = null,
        RetailcrmApiResponse $response = null,
        RetailcrmProxyMiddlewareInterface $next = null
    )
    {
        if ($request->getMethod()) {
            $response = call_user_func_array([$this->client, $request->getMethod()], $request->getData());
        }

        return $next->handle($request, $response, $next->getNext());
    }
}