<?php
$fields = [];
foreach (glob(__DIR__ . '/../app/Filament/Resources/*/*/*.php') as $file) {
    $content = file_get_contents($file);
    preg_match_all('/(?:TextColumn|TextInput|Select|Toggle|DatePicker|IconColumn)::make\(\'([^\']+)\'\)/', $content, $matches);
    foreach ($matches[1] as $field) {
        $fields[$field] = true;
    }
}
$uniqueFields = array_keys($fields);
sort($uniqueFields);
echo implode("\n", $uniqueFields);
