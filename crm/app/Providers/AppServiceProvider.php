<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.rtl');
        Paginator::defaultSimpleView('pagination.rtl');

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Blade::directive('money', fn ($expr) => "<?php echo number_format((float) ({$expr}), 2); ?>");

        view()->composer('*', function ($view) {
            $view->with('appName', Settings::get('app_name', 'Bajaj CRM'));
        });
    }
}
