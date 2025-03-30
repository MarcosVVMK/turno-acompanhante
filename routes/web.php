<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShiftController;
    use App\Http\Controllers\UserController;
    use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/api/shifts', [ShiftController::class, 'index']);


Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/shifts/batch', [ShiftController::class, 'batchStore'])->name('shifts.batch-store');
    Route::get('/api/users', [UserController::class, 'index']);
});



Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');

require __DIR__.'/auth.php';
