<?php

class RetailcrmPipeline
{
    /**
     * @var array
     */
    private $middlewares;

    /**
     * @var callable
     */
    private $action;

    /**
     * @var callable
     */
    private $pipeline;

    /**
     * @param RetailcrmApiRequest $request
     * @return callable
     */
    public function run(RetailcrmApiRequest $request)
    {
        $pipeline = $this->pipeline;
        return $pipeline($request);
    }

    /**
     * @param callable $action
     * @return $this
     */
    public function setAction(callable $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @param array $middlewares
     * @return $this
     */
    public function setMiddlewares(array $middlewares)
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function build()
    {
        $this->pipeline =
            array_reduce(
                array_reverse($this->middlewares),
                $this->buildStack(),
                $this->action
            );
    }

    /**
     * @return callable
     */
    private function buildStack()
    {
        return function ($stack, $middlewareClass) {
            return function ($request) use ($stack, $middlewareClass) {
                $middleware = new $middlewareClass;
                return $middleware($request, $stack);
            };
        };
    }
}
