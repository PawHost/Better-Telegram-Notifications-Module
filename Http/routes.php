<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\BetterTelegramNotifications\Http\Controllers'], function () {
    Route::post('/modules/bettertelegram/recipients', ['uses' => 'BetterTelegramNotificationsController@saveRecipient', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('bettertelegram.recipient.save');
    Route::post('/modules/bettertelegram/recipients/delete', ['uses' => 'BetterTelegramNotificationsController@deleteRecipient', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('bettertelegram.recipient.delete');
    Route::post('/modules/bettertelegram/test', ['uses' => 'BetterTelegramNotificationsController@test', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('bettertelegram.test');
});
