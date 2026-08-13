<?php

namespace Plugins\rss;

use \Typemill\Plugin;
use \Typemill\Models\Storage;
use \Typemill\Models\Meta;
use \Typemill\Models\Settings;
use \Typemill\Models\StorageWrapper;
use \Typemill\Models\Navigation;

class rss extends Plugin
{

    # subscribe to the events
    public static function getSubscribedEvents()
    {
        return array(
            'onPluginsLoaded'   => 'onPluginsLoaded',
            'onPagePublished'   => 'onPagePublished',
            'onPageUnpublished' => 'onPageUnpublished',
            'onPageSorted'      => 'onPageSorted',
            'onPageDeleted'     => 'onPageDeleted',
            'onItemLoaded'      => 'onItemLoaded'
        );
    }

    # If the plugin was just activated, no RSS cache exists yet.
    # Generate it once on the first request (before any page publish event).
    public function onPluginsLoaded($pluginsEvent)
    {
        $storage = new StorageWrapper('\Typemill\Models\Storage');
        if (!$storage->checkFile('cacheFolder', 'rss', 'all.rss'))
        {
            $this->updateRssXmls();
        }
    }

    # at any of theses events, delete the old rss cache files
    public function onPagePublished($item)
    {
        $this->updateRssXmls();
    }

    public function onPageUnpublished($item)
    {
        $this->updateRssXmls();
    }

    public function onPageSorted($inputParams)
    {
        $this->updateRssXmls();
    }

    public function onPageDeleted($item)
    {
        $this->updateRssXmls();
    }

    public function onItemLoaded($itemService)
    {
        $item = $itemService->getData();

        # On first activation no RSS cache exists yet. Generate it on the first page load.
        $storage = new StorageWrapper('\Typemill\Models\Storage');
        if (!$storage->checkFile('cacheFolder', 'rss', 'all.rss'))
        {
            $this->updateRssXmls();
        }

        if (isset($item->elementType) && $item->elementType == 'folder' && isset($item->urlAbs))
        {
            $this->addMeta('rss', '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars($item->name ?? '', ENT_XML1 | ENT_QUOTES) . '" href="' . htmlspecialchars($item->urlAbs . '/rss', ENT_XML1 | ENT_QUOTES) . '">');
        }
    }

    public static function addNewRoutes()
    {
        global $container;

        if (!isset($container))
        {
            return [];
        }

        $routes             = [];
        $navigationService  = new Navigation();
        $urlInfo            = $container->get('urlinfo');
        $settingsService    = new Settings();
        $settings           = $settingsService->loadSettings();

        if (!is_array($settings))
        {
            $settings = [];
        }

        $navigationLive = $navigationService->getLiveNavigation($urlInfo, $settings['langattr'] ?? '');
        if (!is_array($navigationLive))
        {
            $navigationLive = [];
        }

        foreach ($navigationLive as $item)
        {
            if (isset($item->elementType) && $item->elementType == 'folder' && isset($item->urlRelWoF) && isset($item->slug))
            {
                $routes[] = [
                    'httpMethod'    => 'get',
                    'route'         => $item->urlRelWoF . '/rss',
                    'class'         => 'Plugins\rss\rssController:' . $item->slug,
                    'name'          => $item->slug
                ];
            }
        }

        $routes[] = [
            'httpMethod'    => 'get',
            'route'         => '/rss',
            'class'         => 'Plugins\rss\rssController:all',
            'name'          => 'all'
        ];

        return $routes;
    }

    private function updateRssXmls()
    {
        $storage            = new StorageWrapper('\Typemill\Models\Storage');
        $settingsService    = new Settings();
        $settings           = $settingsService->loadSettings();

        if (!is_array($settings))
        {
            $settings = [];
        }

        $navigationService  = new Navigation();
        $navigation         = $navigationService->getLiveNavigation($this->urlinfo, $settings['langattr'] ?? '');

        if (!is_array($navigation))
        {
            return;
        }

        $allItems           = [];

        foreach ($navigation as $page)
        {
            if (isset($page->elementType) && $page->elementType == 'folder' && isset($page->folderContent) && is_array($page->folderContent))
            {
                $metaManager    = new Meta();
                $pageMeta       = $metaManager->getMetadata($page);
                $items          = [];

                foreach ($page->folderContent as $item)
                {
                    $itemMeta = $metaManager->getMetadata($item);
                    if (!empty($itemMeta['meta']['hide']))
                    {
                        continue;
                    }

                    $pubDate = $itemMeta['meta']['created'] ?? '';
                    $time    = $itemMeta['meta']['time'] ?? '';

                    if (!empty($itemMeta['meta']['modified']))
                    {
                        $pubDate = $itemMeta['meta']['modified'];
                    }
                    if (!empty($itemMeta['meta']['manualdate']))
                    {
                        $pubDate = $itemMeta['meta']['manualdate'];
                    }

                    $entry = [
                        'title'         => isset($item->name) ? htmlspecialchars($item->name, ENT_XML1 | ENT_QUOTES) : '',
                        'link'          => isset($item->urlAbs) ? $item->urlAbs : '',
                        'description'   => htmlspecialchars($itemMeta['meta']['description'] ?? '', ENT_XML1 | ENT_QUOTES),
                        'pubDate'       => $this->createRssCompliantDate($pubDate, $time),
                    ];

                    $sortKey = $pubDate . '-' . $time;
                    if ($sortKey === '-')
                    {
                        $sortKey = isset($item->urlAbs) ? $item->urlAbs : uniqid('', true);
                    }

                    $allItems[$sortKey] = $items[] = $entry;
                }

                $description = (is_array($pageMeta) && isset($pageMeta['meta']['description'])) ? htmlspecialchars($pageMeta['meta']['description'], ENT_XML1 | ENT_QUOTES) : '';
                $rssXml = $this->getRssXml(
                    isset($page->name) ? htmlspecialchars($page->name, ENT_XML1 | ENT_QUOTES) : '',
                    isset($page->urlAbs) ? $page->urlAbs : '',
                    $description,
                    $items
                );

                $storage->writeFile('cacheFolder', 'rss', $page->slug . '.rss', $rssXml);
            }
        }
        krsort($allItems);

        $maintitle          = isset($settings['plugins']['rss']['mainrsstitle']) ? htmlspecialchars($settings['plugins']['rss']['mainrsstitle'], ENT_XML1 | ENT_QUOTES) : '';
        $maindescription    = isset($settings['plugins']['rss']['mainrssdescription']) ? htmlspecialchars($settings['plugins']['rss']['mainrssdescription'], ENT_XML1 | ENT_QUOTES) : '';
        $rssXml = $this->getRssXml(
            $maintitle,
            $this->urlinfo['baseurl'],
            $maindescription,
            $allItems
        );
        
        $storage->writeFile('cacheFolder', 'rss', 'all.rss', $rssXml);

    }

    private function getRssXml(string $title, string $link, string $description, array $items)
    {
        $itemsXml = '';
        foreach ($items as $item) {
            $itemsXml .= '
                <item>
                    <title>' . $item['title'] . '</title>
                    <link>' . htmlspecialchars($item['link'] ?? '', ENT_XML1 | ENT_QUOTES) . '</link>
                    <description>' . $item['description'] . '</description>
                    <pubDate>' . $item['pubDate'] . '</pubDate>
                    <guid>' . htmlspecialchars($item['link'] ?? '', ENT_XML1 | ENT_QUOTES) . '</guid>
                </item>
                ';
        }
        return '<?xml version="1.0"?>
            <rss version="2.0">
                <channel>
                    <title>' . $title . '</title>                 
                    <link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES) . '</link>
                    <description>' . $description . '</description>
                    ' . $itemsXml . '
                </channel>
            </rss>
        ';
    }

    private function createRssCompliantDate(string $metaYMS, string $metaHMS)
    {
        $dateString = trim($metaYMS) . ' ' . trim($metaHMS);

        $date = \DateTime::createFromFormat('Y-m-d H-i-s', $dateString);

        if ($date === false)
        {
            $date = new \DateTime();
        }

        $date->setTimezone(new \DateTimeZone('GMT'));
        return $date->format('D, d M Y H:i:s \G\M\T');
    }
}
