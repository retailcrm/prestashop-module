<?php

class RetailcrmLoggerMiddleware extends RetailcrmAbstractProxyMiddleware
{
    public function process(RetailcrmApiRequest $request, RetailcrmApiResponse $response)
    {
        $method = $request->getMethod();

        if (!is_null($request->getMethod())) {
            RetailcrmLogger::writeCaller($method, print_r($request->getData(), true));
        }

        if (!is_null($response->getRawResponse())) {
            if ($response->isSuccessful()) {
                // Don't print long lists in debug logs (errors while calling this will be easy to detect anyway)
                if (in_array($method, ['statusesList', 'paymentTypesList', 'deliveryTypesList'])) {
                    RetailcrmLogger::writeDebug($method, '[request was successful, but response is omitted]');
                } else {
                    RetailcrmLogger::writeDebug($method, $response->getRawResponse());
                }
            } else {
                RetailcrmLogger::writeCaller($method, $response->getErrorMsg());

                if (isset($response['errors'])) {
                    RetailcrmApiErrors::set($response['errors'], $response->getStatusCode());
                    $error = RetailcrmLogger::reduceErrors($response['errors']);
                    RetailcrmLogger::writeNoCaller($error);
                }
            }
        }
        return $this->getNext()->process($request, $response);

    }
}