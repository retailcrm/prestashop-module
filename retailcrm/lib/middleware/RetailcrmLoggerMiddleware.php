<?php

class RetailcrmLoggerMiddleware extends RetailcrmAbstractProxyMiddleware
{
    public function handle(
        RetailcrmApiRequest $request = null,
        RetailcrmApiResponse $response = null,
        RetailcrmProxyMiddlewareInterface $next = null
    )
    {

        if (!is_null($response)) {
            $method = $request->getMethod();
            RetailcrmLogger::writeCaller($method, print_r($response->success, true));

            if (!$response->isSuccessful()) {
                RetailcrmLogger::writeCaller($method, $response->getErrorMsg());

                if (isset($response['errors'])) {
                    RetailcrmApiErrors::set($response['errors'], $response->getStatusCode());
                    $error = RetailcrmLogger::reduceErrors($response['errors']);
                    RetailcrmLogger::writeNoCaller($error);
                }
            } else {
                // Don't print long lists in debug logs (errors while calling this will be easy to detect anyway)
                if (in_array($method, ['statusesList', 'paymentTypesList', 'deliveryTypesList'])) {
                    RetailcrmLogger::writeDebug($method, '[request was successful, but response is omitted]');
                } else {
                    RetailcrmLogger::writeDebug($method, $response->getRawResponse());
                }
            }
        }

        return $response;
    }
}