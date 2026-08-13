# Typemill Plugin RSS

This plugin for [Typemill](https://typemill.net) V2.x allows you to build and publish an RSS feed with the content of your blog.

## How it works

The plugin implements a new route (`/rss`) for all folders that generates an RSS feed for the specific folder.
In each HTML page, a new meta tag is also added to advertise the availability of the RSS feed.

The homepage also has an RSS feed with all published posts/pages.

## Configuration

The title and description of the generic RSS feed can be set in the plugin settings.

## Special thanks

The plugin is largely based on the foundational work by [azettl](https://github.com/azettl/typemill.plugin.rss)
for Typemill V1.x.

## Changelog

### Version 2.2.0

* Improved PHP 8.2 – 8.5 compatibility
* Hardened null/empty handling for missing metadata, settings, and navigation
* RSS cache is now generated automatically when the plugin is activated (no page publish required)
* Added a 404 fallback when an RSS cache file is not present
* XML-escaped links and guids in the feed

### Version 2.0.0

* Refactored for Typemill 2
