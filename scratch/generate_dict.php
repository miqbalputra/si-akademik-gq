<?php
require 'vendor/autoload.php';
use Illuminate\Support\Str;

$fields = [];
foreach (glob(__DIR__ . '/../app/Filament/Resources/*/*/*.php') as $file) {
    $content = file_get_contents($file);
    preg_match_all('/(?:TextColumn|TextInput|Select|Toggle|DatePicker|IconColumn)::make\(\'([^\']+)\'\)/', $content, $matches);
    foreach ($matches[1] as $field) {
        $parts = explode('.', $field);
        $lastPart = end($parts);
        if ($lastPart === 'id' && count($parts) > 1) continue; // Skip raw IDs if not needed
        $headline = (string) Str::of($lastPart)->beforeLast('_id')->headline();
        $fields[$headline] = true;
    }
}
$uniqueFields = array_keys($fields);
sort($uniqueFields);

$dict = [];
foreach ($uniqueFields as $field) {
    $dict[$field] = $field; // Placeholder for manual translation
}
echo json_encode($dict, JSON_PRETTY_PRINT);
