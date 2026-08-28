<?php

namespace App\Providers;

use App\Actions\Workspace\ConsumePendingInvitations;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        Event::listen(Verified::class, fn (Verified $event) => app(ConsumePendingInvitations::class)($event->user));

        $this->app->bind(PdfFactory::class, fn () => (new PdfFactory)->withBrowsershot(
            function (Browsershot $browsershot) {
                // php-fpm's www-data user's home directory (/var/www) isn't
                // writable by www-data itself, which makes Chrome fail
                // outright when it tries to create its user-data-dir and
                // crash-report database there - "Failed to create headless
                // user data directory container" / "chrome_crashpad_handler:
                // --database is required". Point HOME at a directory unique
                // to this render (not a shared static path) right before
                // Browsershot spawns the Node/Chrome process. Symfony's
                // Process inherits $_SERVER/$_ENV for its default
                // environment, not a live getenv() read, so putenv() alone
                // isn't enough - all three need setting.
                //
                // Deliberately not a shared path like plain /tmp: this used
                // to point HOME at /tmp directly, set once in boot() (which
                // runs for every artisan/CLI process, not just PDF renders).
                // Any other process that also honors $HOME - e.g. `php
                // artisan tinker`'s PsySH, commonly run as root - could
                // create its own subdirectory there first with permissions
                // Chrome (running as www-data) then couldn't touch, breaking
                // every render afterwards with this exact error. Scoping the
                // override to only this closure (which only runs when a PDF
                // is actually being rendered) and giving it a fresh unique
                // directory removes both that collision and the narrower one
                // between two concurrent PDF renders sharing a HOME.
                $home = sys_get_temp_dir().'/browsershot-'.Str::random(16);
                mkdir($home, 0777, recursive: true);

                putenv("HOME={$home}");
                $_SERVER['HOME'] = $home;
                $_ENV['HOME'] = $home;

                // --disable-dev-shm-usage works around Docker's default 64MB
                // /dev/shm, too small for Chrome's rendering needs.
                //
                // --disable-crash-reporter skips Chrome's crashpad handler
                // entirely, which otherwise needs a writable database
                // directory under $HOME. Not needed for a one-shot headless
                // render anyway.
                //
                // --no-sandbox: Chrome's sandbox needs unprivileged user
                // namespaces, which this image's AppArmor policy restricts
                // ("No usable sandbox! ... unprivileged user namespaces"),
                // failing outright regardless of the other flags above.
                // Acceptable here because Browsershot only ever renders our
                // own server-generated report template, never arbitrary/
                // user-supplied HTML or URLs.
                $browsershot->setOption('args', [
                    '--disable-dev-shm-usage',
                    '--disable-crash-reporter',
                    '--no-sandbox',
                ]);
            }
        ));
    }
}
