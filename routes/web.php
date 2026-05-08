<?php

use App\Http\Controllers\Admin\AIProviderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SEO Routes
|--------------------------------------------------------------------------
*/
Route::get('/robots.txt', function () {
    $baseUrl = config('app.url') ?: url('/');

    return response(
        "User-agent: *\n" .
        "Disallow: /admin/\n" .
        "Disallow: /api/\n" .
        "Disallow: /dashboard\n" .
        "Disallow: /profile\n" .
        "Allow: /\n\n" .
        "Sitemap: {$baseUrl}/sitemap.xml\n",
        200,
        ['Content-Type' => 'text/plain']
    );
});

Route::get('/sitemap.xml', function () {
    $baseUrl = rtrim(config('app.url') ?: url('/'), '/');
    $now = now()->toAtomString();
    $urls = [
        ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => '/upload', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => '/reports', 'priority' => '0.5', 'changefreq' => 'weekly'],
    ];

    $items = collect($urls)->map(function ($url) use ($baseUrl, $now) {
        return "    <url>\n" .
            "        <loc>{$baseUrl}{$url['loc']}</loc>\n" .
            "        <lastmod>{$now}</lastmod>\n" .
            "        <changefreq>{$url['changefreq']}</changefreq>\n" .
            "        <priority>{$url['priority']}</priority>\n" .
            "    </url>";
    })->implode("\n");

    return response(
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
        "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" .
        $items . "\n" .
        "</urlset>",
        200,
        ['Content-Type' => 'application/xml']
    );
});

/*
|--------------------------------------------------------------------------
| Landing Page (Guest-accessible)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

/*
|--------------------------------------------------------------------------
| Upload Routes (Public — tanpa login)
|--------------------------------------------------------------------------
*/
Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
Route::post('/upload', [UploadController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('upload.store');

/*
|--------------------------------------------------------------------------
| Report Routes (Public — akses via visitor_token cookie)
|--------------------------------------------------------------------------
*/
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::get('/reports/{report}/processing', [ReportController::class, 'processing'])->name('reports.processing');
Route::get('/reports/{report}/status', [ReportController::class, 'status'])->name('reports.status');
Route::post('/reports/{report}/retry', [ReportController::class, 'retry'])->name('reports.retry');
Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

/*
|--------------------------------------------------------------------------
| Dashboard (Admin only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/ai-providers', [AIProviderController::class, 'index'])->name('ai-providers.index');
    Route::patch('/ai-providers/{provider}/toggle', [AIProviderController::class, 'toggle'])->name('ai-providers.toggle');
    Route::put('/ai-providers/{provider}', [AIProviderController::class, 'updateProvider'])->name('ai-providers.update');
    Route::post('/ai-providers/priority', [AIProviderController::class, 'updatePriority'])->name('ai-providers.priority');
    Route::post('/ai-providers/{provider}/reset-counters', [AIProviderController::class, 'resetCounters'])->name('ai-providers.reset');
    Route::post('/ai-providers/{provider}/check-key', [AIProviderController::class, 'checkApiKey'])->name('ai-providers.check-key');
});

/*
|--------------------------------------------------------------------------
| Profile Routes (Breeze — for admin)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
