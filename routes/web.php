<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::prefix('api/v1')->name('api.')->group(function (): void {
    Route::get('csrf', [AuthController::class, 'csrf'])->name('csrf');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::get('branches', [BranchController::class, 'index'])->name('branches');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branch');
    Route::get('branches/{branch}/availability', [BranchController::class, 'availability'])->name('availability');
    Route::get('branches/{branch}/schedule', [BranchController::class, 'schedule'])->name('schedule');
    Route::post('courts/{court}/quote', [BranchController::class, 'quote'])->middleware('throttle:quote')->name('quote');

    Route::middleware('auth')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::patch('me', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('me/password', [ProfileController::class, 'password'])->middleware('throttle:sensitive')->name('profile.password');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings');
        Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:book')->name('book');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('booking');
        Route::get('bookings/{booking}/receipt', [BookingController::class, 'receipt'])->name('booking.receipt');
        Route::patch('bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->middleware('throttle:book')->name('booking.reschedule');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'status'])->name('booking.status');

        Route::prefix('manage')->middleware('role:manager,admin')->name('manage.')->group(function (): void {
            Route::get('branches', [ManagementController::class, 'branches'])->name('branches');
            Route::get('calendar', [ManagementController::class, 'calendar'])->name('calendar');
            Route::get('booking-search', [ManagementController::class, 'findBooking'])->name('booking-search');
            Route::get('customers', [ManagementController::class, 'customers'])->name('customers');
            Route::post('bookings/{booking}/payments', [BookingController::class, 'payment'])->name('payments');
            Route::get('courts', [ManagementController::class, 'courts'])->name('courts');
            Route::post('courts', [ManagementController::class, 'saveCourt'])->name('court.create');
            Route::put('courts/{court}', [ManagementController::class, 'saveCourt'])->name('court.update');
            Route::get('services', [ManagementController::class, 'services'])->name('services');
            Route::post('services', [ManagementController::class, 'saveService'])->name('service.create');
            Route::put('services/{service}', [ManagementController::class, 'saveService'])->name('service.update');
            Route::get('maintenances', [ManagementController::class, 'maintenances'])->name('maintenances');
            Route::post('maintenances', [ManagementController::class, 'createMaintenance'])->name('maintenance.create');
            Route::patch('maintenances/{maintenance}', [ManagementController::class, 'maintenanceStatus'])->name('maintenance.status');
            Route::get('reports', [ManagementController::class, 'reports'])->name('reports');
        });

        Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function (): void {
            Route::post('branches', [ManagementController::class, 'saveBranch'])->name('branch.create');
            Route::put('branches/{branch}', [ManagementController::class, 'saveBranch'])->name('branch.update');
            Route::get('users', [ManagementController::class, 'users'])->name('users');
            Route::patch('users/{user}/role', [ManagementController::class, 'userRole'])->name('user.role');
        });
    });
});
