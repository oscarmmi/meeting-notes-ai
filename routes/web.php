<?php

use App\Http\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MeetingController::class, 'index'])->name('home');
Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
