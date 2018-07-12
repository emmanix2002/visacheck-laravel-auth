<?php

namespace Visacheck\Visacheck\LaravelAuth;


use Hostville\Dorcas\LaravelCompat\Auth\VisacheckUser;
use Hostville\Dorcas\LaravelCompat\Auth\VisacheckUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\ServiceProvider;
use Visacheck\Visacheck\Sdk;

class VisacheckAuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap required services
     */
    public function boot()
    {
        // publish the config file
        $this->publishes([
            __DIR__ . '/config/visacheck-api.php' => config_path('visacheck-api.php'),
        ]);
        
        // check if the Sdk has already been added to the container
        if (!$this->app->has(Sdk::class)) {
            $tokenStoreId = Cookie::get('store_id');
            
            $this->app->singleton(Sdk::class, function ($app) use ($tokenStoreId) {
                $token = !empty($tokenStoreId) ? Cache::get('visacheck.auth_token.'.$tokenStoreId, null) : null;
                # get the token from the cache, if available
                $config = $app->make('config');
                # get the configuration object
                $config = [
                    'environment' => $config->get('visacheck-api.env'),
                    'credentials' => [
                        'id' => $config->get('visacheck-api.client.id'),
                        'secret' => $config->get('visacheck-api.client.secret'),
                        'token' => $token
                    ]
                ];
                return new Sdk($config);
            });
        }
        // add the Visacheck API user provider
        $this->app->when(VisacheckUser::class)
                    ->needs(Sdk::class)
                    ->give(function () {
                        return $this->app->make(Sdk::class);
                    });
        # provide the requirement
        Auth::provider('visacheck', function ($app, array $config) {
            return new VisacheckUserProvider($app->make(Sdk::class), $config);
        });
    }
}