<?php

use vedebel\sitemap\crawlers\DiDomCrawler;

require_once 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

$start = microtime(true);

echo "====================Script started at " . date("H:i:s") . "============================\n\n";
$options = getopt('', [
    'url:',
    'dest:',
    'async::',
    'limit::',
    'debug::',
    'rescan::',
    'threadsLimit::'
]);

if (empty($options['url']) || empty($options['dest'])) {
    exit('Url or File Name is not specified');
}

$url = $options['url'];

$limit = isset($options['limit']) ? $options['limit'] : 100;
$threadsLimit = isset($options['threadsLimit']) ? $options['threadsLimit'] : null;

$dest = $options['dest'];

$debug = isset($options['debug']);

$parser = new DiDomCrawler(new \DiDom\Document());

$storage = new vedebel\sitemap\storages\BasicLinksStorage();

$redis = new Redis();
$redis->pconnect('localhost');
$redis->setOption(Redis::OPT_PREFIX, 'site_crawler:');

$storage = new vedebel\sitemap\storages\RedisLinksStorage($redis, 'test');

$config = new \vedebel\sitemap\Config(new \vedebel\sitemap\Url($url), [
    'limits' => [
        'threads' => $threadsLimit,
        'linksTotal' => $limit,
        'linksPerFile' => 50000
    ],
    'debug' => [
        'enable' => true
    ],
    'onProgress' => [
        'frequency' => 100,
        'callable' => function (array $scanned, array $added, array $queue) {
            echo "This is message form callback.\nScanned: "
                . count($scanned) . "\nAdded: " . count($added) . "\nQueue: " . count($queue) . "\n";
        }
    ]
]);

$generator = new vedebel\sitemap\Sitemap($parser, $storage, $config);
$generator->setLoader(new GuzzleHttp\Client(['cookies' => true]));
if (isset($options['rescan'])) {
    $generator->rescan();
} else {
    $generator->crawl();
}
$generator->saveXml($dest);

echo "====================Script ended at " . date("H:i:s") . "============================\n";
echo "- Execution time: " . round((microtime(true) - $start) / 60, 2) . " minutes\n";
echo "- Peak memory usage: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "- Url: {$url}\n";
echo "- Limit: {$limit}\n";