<?php

namespace OEngine\Crawler;

use OEngine\Crawler\Models\DataRawSite;
use OEngine\Crawler\Models\SiteManager;
use OEngine\Crawler\Supports\Browser\Client;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler as DomCrawlerBase;

class CrawlerManager
{
    public function RemoveNode(DomCrawlerBase $crawler, $query, $onlyFirst = false)
    {
        if ($query != "") {
            $crawler->filter($query)->each(function (DomCrawlerBase $crawler) use ($onlyFirst) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                    if ($onlyFirst) {
                        break;
                    }
                }
            });
        }

        return $crawler;
    }
    public function RemoveLinkLocalNode(DomCrawlerBase $crawler)
    {
        $crawler->filter('a')->each(function (DomCrawlerBase $crawler) {
            if (substr($crawler->attr('href'), 0, 4) != "http" && substr($crawler->attr('href'), 0, 1) != "#") {
                $crawler->getNode(0)->setAttribute('href', '#');
            }
        });

        return $crawler;
    }
    public function RemoveAdsAndFB(DomCrawlerBase $crawler)
    {
        $this->RemoveNode($crawler, '.lb-ad');
        $this->RemoveNode($crawler, '#social-platforms');
        $this->RemoveNode($crawler, '#fb-root');
        $this->RemoveNode($crawler, 'script');
    }

    // Remove unwanted HTML comments
    public function RemoveHtmlComments($content = '')
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $content);
    }
    public function getDomFromHtml($html)
    {
        return new DomCrawlerBase($html);
    }
    public function getContentFromLink($link)
    {
        $client = new Client();
        return $client->request('GET', $link);
    }
    private function getInfoFromLink($link)
    {
        return  parse_url($link);
    }
    public function checkKeyOrCreateTabale($domain_key)
    {
        $table_name = 'crawl_data_raw_site_manager_' . $domain_key;
        if (!Schema::hasTable($table_name)) {
            Schema::create($table_name, function (Blueprint $table) use ($table_name) {
                $table->id();
                $table->string('link_key');
                $table->string('domain_key');
                $table->string('domain_site')->default();
                $table->string('link');
                $table->string('title');
                $table->string('description');
                $table->longText('data_raw');
                $table->unique('link_key', 'link_key_unique');
                $table->timestamps();
            });
        }
    }
    public function checkLink($link)
    {

        $parts = $this->getInfoFromLink($link);
        $domain_key = md5($parts['host']);
        $table_name = 'crawl_data_raw_site_manager_' . $domain_key;
        if (!Schema::hasTable($table_name)) {
            return false;
        }
        $link_key = md5($link);
        return DataRawSite::NewDataRawSite($domain_key)->where('link_key', $link_key)->first();
    }
    public function getDataRawByDomain($link)
    {
        $parts = $this->getInfoFromLink($link);
        $domain_key = md5($parts['host']);
        $site =  SiteManager::where('key', $domain_key)->first();
        if ($site)
            return $site->DataRaw()->get();
        return [];
    }
    public function insertLink($link)
    {
        try {
            $dataRaw = $this->checkLink($link);
            if ($dataRaw) {
                $siteDom = $this->getDomFromHtml($dataRaw->data_raw);
                $links = $siteDom->filter('a');
                return ['links' =>  $links, 'dataRaw' => $dataRaw];
            }
            $parts = $this->getInfoFromLink($link);
            $domain_key = md5($parts['host']);
            $site =  SiteManager::where('key', $domain_key)->first();
            if (!$site) {
                $site = new SiteManager();
                $site->key = $domain_key;
                $site->domain_site = $parts['host'];
                $site->link_site = $parts['scheme'] . '://' . $parts['host'];
                $site->save();
            }
            $link_key = md5($link);
            $crawler = $this->getContentFromLink($link);

            $keywords = $crawler->filter('meta[name="keywords"]')->first();
            $description = $crawler->filter('meta[name="description"]')->first();
            $titles = $crawler->filter('title')->first();
            $keywords = $keywords->count() > 0 ? $keywords->attr('content') : "";
            $description = $description->count() > 0 ? $description->attr('content') : "";
            $links = $crawler->filter('a');
            $title = $titles->count() > 0 ? $titles->html() : "";
            $data_raw = $crawler->outerHtml();
            $dataRaw = $site->DataRaw()->updateOrCreate([
                'link_key' => $link_key
            ], [
                'domain_key' => $domain_key, 'domain_site' => $site->link_site, 'link_key' => $link_key, 'link' => $link,
                'title' =>  $title, 'description' =>  $description, 'data_raw' =>  $data_raw,
            ]);
            return ['links' => $links, 'dataRaw' => $dataRaw];
        } catch (\Exception $err) {
            return null;
        }
    }
    public function FindAndCallback($crawler, $selector, $callback)
    {
        $crawler->filter($selector)->each($callback);
    }
    public function saveImageToStorage($crawler, $link, $path = '/scrawler', $disk = 'public')
    {
        $crawler->filter('img')->each(function ($item) use ($link, $disk, $path) {
            //  try {
            $image_src = $item->attr('src');
            if (!Str::startsWith($image_src, 'http')) {
                $image_src =  $link . '/' . $image_src;
            }
            $image_src = Str::replace('../', '', $image_src);
            $image_name = basename($image_src);
            Storage::disk($disk)->put($path . '/' . $image_name, file_get_contents($image_src));
            $item->getNode(0)->setAttribute('src', $path . '/' . $image_name);
            // } catch (\Exception $ex) {
            // }
        });
        return $crawler;
    }
    public function getItemByLink($link)
    {
        ['dataRaw' => $dataRaw] = $this->insertLink($link);
        return $dataRaw;
    }
}
