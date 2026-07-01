<?php

$files = array_merge(
    glob(__DIR__ . '/../app/Filament/Resources/*/Schemas/*.php'),
    glob(__DIR__ . '/../app/Filament/Resources/*/Tables/*.php')
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Find:  Something::make('xxx'),\n    ->label('Yyy')
    // Replace: Something::make('xxx')->label('Yyy'),
    // Note: The regex looks for `),` followed by whitespace/newlines and then `->label(`
    
    $newContent = preg_replace('/(\w+::make\([\'"][a-zA-Z0-9_\.]+[\'"]\)),(\s+->label\([^\)]+\))/', '$1$2,', $content);
    
    // Some lines might not match if they didn't have commas originally? 
    // Wait, the error was because the comma was BEFORE `->label()`.
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Fixed $file\n";
    }
}
echo "Done!\n";
