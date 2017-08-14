<?php

/**
 * Class RequestProxy
 * @package RetailCrm\Component
 */
class RetailcrmProxy
{
    private $api;
    private $log;

    public function __construct($url, $key, $log, $version = '4')
    {   
        switch ($version) {
            case '5':
                $this->api = new RetailcrmApiClientV5($url, $key);
                break;
            case '4':
                $this->api = new RetailcrmApiClientV4($url, $key);
                break;
            case '3':
                $this->api = new RetailcrmApiClientV3($url, $key);
                break;
        }
        
        $this->log = $log;
    }

    public function __call($method, $arguments)
    {   
        $date = date('Y-m-d H:i:s');
        try {
            $response = call_user_func_array(array($this->api, $method), $arguments);

            if (!$response->isSuccessful()) {
                error_log("[$date] @ [$method] " . $response->getErrorMsg() . "\n", 3, $this->log);
                if (isset($response['errors'])) {
                    $error = implode("\n", $response['errors']);
                    error_log($error . "\n", 3, $this->log);
                }
                $response = false;
            }

            return $response;
        } catch (CurlException $e) {
            error_log("[$date] @ [$method] " . $e->getMessage() . "\n", 3, $this->log);
            return false;
        } catch (InvalidJsonException $e) {
            error_log("[$date] @ [$method] " . $e->getMessage() . "\n", 3, $this->log);
            return false;
        }
    }

}
