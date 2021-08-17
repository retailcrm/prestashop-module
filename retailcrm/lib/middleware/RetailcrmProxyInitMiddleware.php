<?php


class RetailcrmProxyInitMiddleware extends RetailcrmAbstractProxyMiddleware
{

    const API_URL = 'RETAILCRM_ADDRESS';
    const API_KEY = 'RETAILCRM_API_TOKEN';

    public $key;

    public $url;

    public $api;

    public function handle($request = null)
    {
        if ($request === null) {

            $this->url = Configuration::get(static::API_URL);
            $this->key = Configuration::get(static::API_KEY);

            $this->api = new RetailcrmApiClientV5($this->url, $this->key);
            return $this;
        }
        return parent::handle($request);
    }
}