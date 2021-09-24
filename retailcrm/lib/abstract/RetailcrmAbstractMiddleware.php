<?php

class RetailcrmAbstractMiddleware implements RetailcrmMiddlewareInterface
{
    /**
     * @param RetailcrmApiRequest $request
     * @param callable|null $next
     * @return RetailcrmApiResponse
     */
    public function __invoke(RetailcrmApiRequest $request, callable $next = null)
    {
        return $this($request, $next);
    }

}