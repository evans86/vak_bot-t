<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::group(['namespace' => 'Activate', 'prefix' => 'activate'], function () {
    Route::get('countries', 'CountryController@index')->name('activate.countries.index');
    Route::get('operators/{operators}', 'OperatorController@index')->name('activate.operators.index');
    Route::get('product', 'ProductController@index')->name('activate.product.index');
});


//Route::group(['namespace' => 'Activate', 'prefix' => 'activate'], function () {
//    Route::resource('countries', 'CountryController')->names('activate.country');
//});
