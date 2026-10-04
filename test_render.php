<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('role','admin')->first();
$token = $user->createToken('rendertest')->plainTextToken;
echo $token;
