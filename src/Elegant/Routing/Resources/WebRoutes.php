<?php

use Elegant\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded in your application. Now create something great!
|
*/

Route::get('/', function () {
    app()->blade->view('welcome');
});

Route::set('404_override', function () {
    show_404();
});

Route::set('translate_uri_dashes', false);
