<?php
$files = array_merge(
    glob(__DIR__ . '/../app/Filament/Resources/*/Schemas/*.php'),
    glob(__DIR__ . '/../app/Filament/Resources/*/Tables/*.php')
);

foreach ($files as $file) {
    exec("php -l " . escapeshellarg($file), $output, $returnVar);
    if ($returnVar !== 0) {
        echo "Broken: $file\n";
    }
}
