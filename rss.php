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
            'onPagePublished' => 'onPagePublished',
            'onPageUnpublished' => 'onPageUnpublished',
            'onPageSorted' => 'onPageSorted',
            'onPageDeleted' => 'onPageDeleted',
            'onItemLoaded' => 'onItemLoaded'
        );
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
        if ($item->elementType == 'folder') {
            $this->addMeta('rss', '<link rel="alternate" type="application/rss+xml" title="' . $item->name . '" href="' . $item->urlAbs . '/rss">');
        }
    }

    public static function addNewRoutes()
    {
        global $container;

        $routes = [];
        $navigationService = new Navigation();
        $urlInfo = $container->get('urlinfo');
        $settingsService = new Settings();
        $settings = $settingsService->loadSettings();

        $navigationLive = $navigationService->getLiveNavigation($urlInfo, $settings['langattr']);
        foreach ($navigationLive as $item) {
            if ($item->elementType == 'folder') {
                $routes[] = [
                    'httpMethod' => 'get',
                    'route' => $item->urlRelWoF . '/rss',
                    'class' => 'Plugins\rss\rssController:' . $item->slug,
                    'name' => $item->slug
                ];
            }
        }
        $routes[] = [
            'httpMethod' => 'get',
            'route' => '/rss',
            'class' => 'Plugins\rss\rssController:all',
            'name' => 'all'
        ];
        return $routes;
    }

    private function updateRssXmls()
    {
        $storage = new StorageWrapper('\Typemill\Models\Storage');
        $settingsService = new Settings();
        $settings = $settingsService->loadSettings();
        $navigationService = new Navigation();
        $navigation = $navigationService->getLiveNavigation($this->urlinfo, $settings['langattr']);
        $allItems = [];
        foreach ($navigation as $page) {
            if ($page->elementType == 'folder') {
                $metaManager = new Meta();
                $pageMeta = $metaManager->getMetadata($page);
                $items = [];
                foreach ($page->folderContent as $item) {
                    $itemMeta = $metaManager->getMetadata($item);
                    $entry = [
                        'title' => htmlspecialchars($item->name, ENT_XML1),
                        'link' => $item->urlAbs,
                        'description' => htmlspecialchars($itemMeta['meta']['description'], ENT_XML1)
                    ];
                    $allItems[(isset($itemMeta['meta']['manualdate']) && $itemMeta['meta']['manualdate'] != null) ? $itemMeta['meta']['manualdate'] . '-' . $itemMeta['meta']['time'] : $itemMeta['meta']['modified'] . '-' . $itemMeta['meta']['time']] = $items[] = $entry;
                }
                $rssXml = $this->getRssXml(
                    htmlspecialchars($page->name, ENT_XML1),
                    $page->urlAbs,
                    htmlspecialchars($pageMeta['meta']['description'], ENT_XML1),
                    $items
                );
                $storage->writeFile('cacheFolder', 'rss', $page->slug . '.rss', $rssXml);
            }
        }
        krsort($allItems);
        $rssXml = $this->getRssXml(
            htmlspecialchars($settings['plugins']['rss']['mainrsstitle'], ENT_XML1),
            $this->urlinfo['baseurl'],
            htmlspecialchars($settings['plugins']['rss']['mainrssdescription'], ENT_XML1),
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
                    <link>' . $item['link'] . '</link>
                    <description>' . $item['description'] . '</description>
                </item>
                ';
        }

        return '<?xml version="1.0"?>
            <rss version="2.0">
                <channel>
                    <title>' . $title . '</title>
                    <link>' . $link . '</link>
                    <description>' . $description . '</description>
                    ' . $itemsXml . '
                </channel>
            </rss>
        ';
    }
}
