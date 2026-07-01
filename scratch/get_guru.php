<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::role('guru')->get();
foreach ($users as $user) {
    echo $user->email . ' | ' . $user->name . "\n";
}
