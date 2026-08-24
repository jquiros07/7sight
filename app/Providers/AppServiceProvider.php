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
        //
        // --disable-crash-reporter skips Chrome's crashpad handler entirely,
        // which otherwise needs a writable database directory under $HOME
        // and fails outright ("chrome_crashpad_handler: --database is
        // required") if that path was created by a different container user
        // first - $HOME=/tmp above is shared by every user in the
        // container, so this collision isn't hypothetical. Not needed for a
        // one-shot headless render anyway.
        //
        // --no-sandbox: Chrome's sandbox needs unprivileged user namespaces,
        // which this image's AppArmor policy restricts ("No usable sandbox!
        // ... unprivileged user namespaces"), failing outright regardless of
        // the other flags above. Acceptable here because Browsershot only
        // ever renders our own server-generated report template, never
        // arbitrary/user-supplied HTML or URLs.
        $this->app->bind(PdfFactory::class, fn () => (new PdfFactory)->withBrowsershot(
            fn (Browsershot $browsershot) => $browsershot->setOption('args', [
                '--disable-dev-shm-usage',
                '--disable-crash-reporter',
                '--no-sandbox',
            ])
        ));
    }
}
