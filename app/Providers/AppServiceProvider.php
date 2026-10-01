<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
   public function boot()
    {
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }

        // Las acciones de una pantalla Livewire llegan por una ruta aparte: se vuelve a revisar el rol de la pantalla original.
        Livewire::addPersistentMiddleware(\Spatie\Permission\Middlewares\RoleMiddleware::class);
    }
}
