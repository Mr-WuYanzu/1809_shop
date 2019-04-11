<?php

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
Route::get('/phpinfo', function () {
    phpinfo();
});
//微信接口返回文件
Route::get('/weixin/valid','WxController@valid');
Route::post('/weixin/valid','WxController@wxEvent');
//获取access_token
Route::any('/access/token','WxController@access_token');
//查询数据库数据
Route::any('/shop',"WxController@shop");
//创建微信菜单
Route::any('/create_menu','WxController@create_menu');