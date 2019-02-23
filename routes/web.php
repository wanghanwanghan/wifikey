<?php

Route::get('/', function () {
    return 'no page';
});

Route::middleware('throttle:20,1')->get('/getArticle/{num}','Controller@getArticle');




//Route::get('/myhandle','Controller@myhandle');











