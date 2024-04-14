<?php

namespace Plugins\rss;

use \Typemill\Models\StorageWrapper;

class rssController
{
    public function __call($name, $arguments)
    {
        $storage = new StorageWrapper('\Typemill\Models\Storage');
        $rssXml = $storage->getFile('cacheFolder', 'rss', $name . '.rss');
        header('Content-Type: text/xml');
        die(trim($rssXml));
    }
}