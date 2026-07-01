<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = \Filament\Tables\Columns\TextColumn::make('level_name');
echo "KEY: " . $c->getLabel() . "\n";

$f = \Filament\Forms\Components\TextInput::make('classroom.name');
echo "KEY2: " . $f->getLabel() . "\n";
