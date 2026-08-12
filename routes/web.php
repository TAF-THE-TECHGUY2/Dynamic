<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\YardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public — Blade + Alpine
|--------------------------------------------------------------------------
|
| Server-rendered HTML with no hydration cost. These pages have to rank and
| load fast on a phone in a yard.
|
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/bulk-diesel', [PageController::class, 'bulkDiesel'])->name('bulk-diesel');
Route::get('/truck-stop', [PageController::class, 'truckStop'])->name('truck-stop');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'sendContactMessage'])
    ->middleware('throttle:6,1')
    ->name('contact.send');

Route::get('/quote', [QuoteController::class, 'show'])->name('quote');
Route::post('/quote/calculate', [QuoteController::class, 'calculate'])
    ->middleware('throttle:60,1')
    ->name('quote.calculate');
Route::post('/quote', [QuoteController::class, 'submit'])
    ->middleware('throttle:6,1')
    ->name('quote.submit');

Route::get('/d/{token}', [PublicDocumentController::class, 'show'])->name('documents.public');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Keep Fortify's conventional route name available for post-authentication
// redirects and old bookmarks while the real dashboard lives under /admin.
Route::redirect('/dashboard', '/admin')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin — Inertia + React
|--------------------------------------------------------------------------
|
| Stateful and component-heavy, and the document builder needs a live preview
| that updates as you type. Every route is authorised again in the controller
| or through a policy — a hidden nav item is not authorisation.
|
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('pricing', [PricingController::class, 'index'])->name('pricing.index');
    Route::post('pricing', [PricingController::class, 'store'])->name('pricing.store');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::patch('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Blade, not Inertia — the same template the PDF renders through.
    Route::get('documents/{document}/print', [DocumentController::class, 'print'])->name('documents.print');
    Route::get('documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');

    Route::post('documents/{document}/issue', [DocumentController::class, 'issue'])->name('documents.issue');
    Route::post('documents/{document}/convert', [DocumentController::class, 'convert'])->name('documents.convert');
    Route::post('documents/{document}/reprice', [DocumentController::class, 'reprice'])->name('documents.reprice');
    Route::post('documents/{document}/status', [DocumentController::class, 'status'])->name('documents.status');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('yard', [YardController::class, 'index'])->name('yard.index');
    Route::post('yard', [YardController::class, 'store'])->name('yard.store');
    Route::post('yard/{booking}/check-out', [YardController::class, 'checkOut'])->name('yard.check-out');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/settings.php';
