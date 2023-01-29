<?php

namespace OEngine\Crawler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * 
 * @method static \Symfony\Component\DomCrawler\Crawler RemoveNode( $crawler, $query, $onlyFirst = false)
 * @method static \Symfony\Component\DomCrawler\Crawler RemoveLinkLocalNode($crawler)
 * @method static \Symfony\Component\DomCrawler\Crawler RemoveAdsAndFB($crawler)
 * @method static string RemoveHtmlComments(string $content)
 * @method static \Symfony\Component\DomCrawler\Crawler getDomFromHtml($html)
 * @method static mixed getDataRawByDomain($link)
 * @method static \OEngine\Crawler\Models\DataRawSite getItemByLink($link)
 * @method static mixed insertLink($link)
 * @method static bool checkLink($link)
 * @method static mixed checkKeyOrCreateTabale($domain_key)
 * @method static mixed saveImageToStorage($crawler, $link, $path = '/', $disk = 'local')
 * 
 * @see \OEngine\Crawler\Facades\Crawler
 */
class Crawler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \OEngine\Crawler\CrawlerManager::class;
    }
}
