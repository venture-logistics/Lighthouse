<?php

// HMRC OAuth routes
Route::middleware('auth')->group(function () {
    Route::get('/hmrc/connect',    [HmrcAuthController::class, 'redirect'])->name('hmrc.connect');
    Route::get('/hmrc/callback',   [HmrcAuthController::class, 'callback'])->name('hmrc.callback');
    Route::post('/hmrc/disconnect',[HmrcAuthController::class, 'disconnect'])->name('hmrc.disconnect');
});

?>