<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});


$app->get('/setredis', function () use ($app) {
    Cache::put('lumen', 'Hello, puff.', 5);
    return "done";
});

$app->get('/redis', function () use ($app) {
    return Cache::get('lumen');
});

$app->get('ping', 'PingController@ping');

$app->post('chats', 'ChatsController@chat');