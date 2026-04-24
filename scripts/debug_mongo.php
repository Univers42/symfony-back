<?php
require '/var/www/html/vendor/autoload.php';
$c = new MongoDB\Client('mongodb://baas:baas_app_pwd@mongo:27017/baas?authSource=baas');
$start = microtime(true);
$rows = iterator_to_array($c->baas->gallery_images->find());
$dt = microtime(true) - $start;
echo 'Mongo find took ' . round($dt * 1000) . " ms, count=" . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    echo "  - " . ($r['title'] ?? '?') . PHP_EOL;
}

echo "--- Trying ODM DocumentManager ---\n";
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$dm = $container->get('doctrine_mongodb.odm.document_manager');
$start = microtime(true);
$docs = $dm->getRepository(\App\Document\GalleryImage::class)->findAll();
$dt = microtime(true) - $start;
echo 'ODM findAll took ' . round($dt * 1000) . " ms, count=" . count($docs) . PHP_EOL;
foreach ($docs as $d) {
    echo "  - " . $d->getTitle() . PHP_EOL;
}
