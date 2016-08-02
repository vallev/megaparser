<?php

namespace parserbot\megaparser;

class EffectiveUrlMiddleware
{
    /**
     * @var \Psr\Http\Message\RequestInterface
     */
    private $lastRequest;

    /**
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function __invoke(\Psr\Http\Message\RequestInterface $request)
    {
        $this->lastRequest = $request;
        return $request;
    }

    /**
     * @return \Psr\Http\Message\RequestInterface
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }
}