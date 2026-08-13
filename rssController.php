<?php

namespace Plugins\rss;

use \Typemill\Models\StorageWrapper;

class rssController
{
    public function __call($name, $arguments)
    {
        $storage = new StorageWrapper('\Typemill\Models\Storage');
        $rssXml = $storage->getFile('cacheFolder', 'rss', $name . '.rss');

        if ($rssXml === false || $rssXml === null)
        {
            http_response_code(404);
            header('Content-Type: text/plain');
            die('Not found');
        }

        $rssXml = trim($rssXml);

        header('Content-Type: text/xml');
        die($rssXml);
    }
}