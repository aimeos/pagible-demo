<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const DEMO_THEME_NAMESPACE = 'demo';
    private const BASE_THEME_PATHS = [
        'vendor/aimeos/pagible-theme/views',
        'vendor/aimeos/pagible-theme/src/views',
    ];

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
        $this->ensureDemoThemeNamespace();
    }

    private function ensureDemoThemeNamespace(): void
    {
        $hints = View::getFinder()->getHints();

        if (array_key_exists(self::DEMO_THEME_NAMESPACE, $hints)) {
            return;
        }

        foreach (self::BASE_THEME_PATHS as $path) {
            $baseThemeViews = base_path($path);
            if (is_dir($baseThemeViews)) {
                View::addNamespace(self::DEMO_THEME_NAMESPACE, $baseThemeViews);
                break;
            }
        }
    }
}
