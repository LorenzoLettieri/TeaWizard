<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (env('VERCEL')) {
            $runtimeRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'teawizard';
            $compiledViewsPath = $runtimeRoot.DIRECTORY_SEPARATOR.'views';
            $sessionPath = $runtimeRoot.DIRECTORY_SEPARATOR.'sessions';
            $cachePath = $runtimeRoot.DIRECTORY_SEPARATOR.'cache';
            $privateDiskRoot = $runtimeRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private';
            $publicDiskRoot = $runtimeRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

            foreach ([$runtimeRoot, $compiledViewsPath, $sessionPath, $cachePath, $privateDiskRoot, $publicDiskRoot] as $path) {
                if (! is_dir($path)) {
                    @mkdir($path, 0777, true);
                }
            }

            Config::set('view.compiled', $compiledViewsPath);
            Config::set('session.files', $sessionPath);
            Config::set('cache.stores.file.path', $cachePath);
            Config::set('cache.stores.file.lock_path', $cachePath);
            Config::set('filesystems.disks.local.root', $privateDiskRoot);
            Config::set('filesystems.disks.public.root', $publicDiskRoot);

            if (! env('SESSION_DRIVER')) {
                Config::set('session.driver', 'cookie');
            }

            if (! env('CACHE_STORE')) {
                Config::set('cache.default', 'array');
            }

            if (! env('LOG_CHANNEL')) {
                Config::set('logging.default', 'stderr');
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        if (env('VERCEL')) {
            URL::forceScheme('https');
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
