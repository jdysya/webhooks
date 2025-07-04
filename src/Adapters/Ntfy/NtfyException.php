<?php

/*
 * This file is part of fof/webhooks.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Webhooks\Adapters\Ntfy;

use Exception;
use Psr\Http\Message\ResponseInterface;

class NtfyException extends Exception
{
    private $http;
    private $url;

    /**
     * Exception constructor.
     *
     * @param ResponseInterface $res
     * @param string            $url
     */
    public function __construct(ResponseInterface $res, string $url)
    {
        $this->http = $res->getStatusCode();
        $this->url = $url;
        $contents = $res->getBody()->getContents();

        parent::__construct($contents ?: $res->getReasonPhrase());
    }

    public function __toString()
    {
        return "HTTP $this->http – $this->message ($this->url)";
    }
}