<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\PdfFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // php-fpm's www-data user's home directory (/var/www) isn't writable
        // by www-data itself, which makes Chrome fail outright when it tries
        // to create its user-data-dir and crash-report database there -
        // "Failed to create headless user data directory container" /
        // "chrome_crashpad_handler: --database is required". Point HOME at
        // /tmp (always writable) before Browsershot spawns the Node/Chrome
        // process. Symfony's Process inherits $_SERVER/$_ENV for its default
        // environment, not a live getenv() read, so putenv() alone isn't
        // enough - all three need setting.
        putenv('HOME=/tmp');
        $_SERVER['HOME'] = '/tmp';
        $_ENV['HOME'] = '/tmp';

        // --disable-dev-shm-usage works around Docker's default 64MB
        // /dev/shm, too small for Chrome's rendering needs.
        $this->app->bind(PdfFactory::class, fn () => (new PdfFactory)->withBrowsershot(
            fn (Browsershot $browsershot) => $browsershot->setOption('args', [
                '--disable-dev-shm-usage',
            ])
        ));
    }
}
