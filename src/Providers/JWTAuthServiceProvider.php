<?php

namespace Tymon\JWTAuth\Providers;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Claims\Factory;
use Tymon\JWTAuth\Commands\JWTGenerateCommand;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\JWTAuthFilter;
use Tymon\JWTAuth\Middleware\JWTAuthMiddleware;
use Tymon\JWTAuth\JWTManager;
use Tymon\JWTAuth\PayloadFactory;
use Tymon\JWTAuth\Validators\PayloadValidator;

class JWTAuthServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the service provider.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('jwt.php')
        ]);

        $this->bootBindings();

        $this->commands('tymon.jwt.generate');
    }

    /**
     * Bind some Interfaces and implementations
     */
    protected function bootBindings()
    {
        $this->app['Tymon\JWTAuth\JWTAuth'] = function ($app) {
            return $app['tymon.jwt.auth'];
        };

        $this->app['Tymon\JWTAuth\Providers\User\UserInterface'] = function ($app) {
            return $app['tymon.jwt.provider.user'];
        };

        $this->app['Tymon\JWTAuth\Providers\JWT\JWTInterface'] = function ($app) {
            return $app['tymon.jwt.provider.jwt'];
        };

        $this->app['Tymon\JWTAuth\Providers\Auth\AuthInterface'] = function ($app) {
            return $app['tymon.jwt.provider.auth'];
        };

        $this->app['Tymon\JWTAuth\Providers\Storage\StorageInterface'] = function ($app) {
            return $app['tymon.jwt.provider.storage'];
        };

        $this->app['Tymon\JWTAuth\JWTManager'] = function ($app) {
            return $app['tymon.jwt.manager'];
        };

        $this->app['Tymon\JWTAuth\Blacklist'] = function ($app) {
            return $app['tymon.jwt.blacklist'];
        };

        $this->app['Tymon\JWTAuth\PayloadFactory'] = function ($app) {
            return $app['tymon.jwt.payload.factory'];
        };

        $this->app['Tymon\JWTAuth\Claims\Factory'] = function ($app) {
            return $app['tymon.jwt.claim.factory'];
        };

        $this->app['Tymon\JWTAuth\Validators\PayloadValidator'] = function ($app) {
            return $app['tymon.jwt.validators.payload'];
        };
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // register providers
        $this->registerUserProvider();
        $this->registerJWTProvider();
        $this->registerAuthProvider();
        $this->registerStorageProvider();
        $this->registerJWTBlacklist();

        $this->registerClaimFactory();
        $this->registerPayloadFactory();
        $this->registerJWTManager();

        $this->registerJWTAuth();
        $this->registerPayloadValidator();
        $this->registerJWTAuthMiddleware();
        $this->registerJWTCommand();

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'jwt');
    }

    /**
     * Register the bindings for the User provider
     */
    protected function registerUserProvider()
    {
        $this->app['tymon.jwt.provider.user'] = $this->app->share(function ($app) {
            return $app->make($this->config('providers.user'), [$app->make($this->config('user'))]);
        });
    }

    /**
     * Register the bindings for the JSON Web Token provider
     */
    protected function registerJWTProvider()
    {
        $this->app['tymon.jwt.provider.jwt'] = $this->app->share(function ($app) {

            $secret = $this->config('secret');
            $algo = $this->config('algo');
            $provider = $this->config('providers.jwt');

            return $app->make($provider, [$secret, $algo]);
        });
    }

    /**
     * Register the bindings for the Auth provider
     */
    protected function registerAuthProvider()
    {
        $this->app['tymon.jwt.provider.auth'] = $this->app->share(function ($app) {
            return $app->make($this->config('providers.auth'), [ $app['auth'] ]);
        });
    }

    /**
     * Register the bindings for the Storage provider
     */
    protected function registerStorageProvider()
    {
        $this->app['tymon.jwt.provider.storage'] = $this->app->share(function ($app) {
            return $app->make($this->config('providers.storage'), [ $app['cache'] ]);
        });
    }

    /**
     * Register the bindings for the Payload Factory
     */
    protected function registerClaimFactory()
    {
        $this->app->singleton('tymon.jwt.claim.factory', function () {
            return new Factory();
        });
    }

    /**
     * Register the bindings for the Payload Factory
     */
    protected function registerPayloadFactory()
    {
        $this->app['tymon.jwt.payload.factory'] = $this->app->share(function ($app) {
            $factory = new PayloadFactory($app['tymon.jwt.claim.factory'], $app['request']);

            return $factory->setTTL($this->config('ttl'));
        });
    }

    /**
     * Register the bindings for the JWT Manager
     */
    protected function registerJWTManager()
    {
        $this->app['tymon.jwt.manager'] = $this->app->share(function ($app) {

            $instance =  new JWTManager(
                $app['tymon.jwt.provider.jwt'],
                $app['tymon.jwt.blacklist'],
                $app['tymon.jwt.payload.factory']
            );

            return $instance->setBlacklistEnabled($this->config('blacklist_enabled'));
        });
    }

    /**
     * Register the bindings for the main JWTAuth class
     */
    protected function registerJWTAuth()
    {
        $this->app['tymon.jwt.auth'] = $this->app->share(function ($app) {

            $auth = new JWTAuth(
                $app['tymon.jwt.manager'],
                $app['tymon.jwt.provider.user'],
                $app['tymon.jwt.provider.auth'],
                $app['request']
            );

            return $auth->setIdentifier($this->config('identifier'));
        });
    }

    /**
     * Register the bindings for the main JWTAuth class
     */
    protected function registerJWTBlacklist()
    {
        $this->app['tymon.jwt.blacklist'] = $this->app->share(function ($app) {
            return new Blacklist($app['tymon.jwt.provider.storage']);
        });
    }

    /**
     * Register the bindings for the payload validator
     */
    protected function registerPayloadValidator()
    {
        $this->app['tymon.jwt.validators.payload'] = $this->app->share(function ($app) {
            return with(new PayloadValidator)->setRequiredClaims($this->config('required_claims'));
        });
    }

    protected function registerJWTAuthMiddleware()
    {
        $this->app['tymon.jwt.middleware'] = $this->app->share(function ($app) {
            $response = $app->make('Illuminate\Routing\ResponseFactory');
            return new JWTAuthMiddleware($response, $app['events'], $app['tymon.jwt.auth']);
        });
    }

    /**
     * Register the Artisan command
     */
    protected function registerJWTCommand()
    {
        $this->app['tymon.jwt.generate'] = $this->app->share(function ($app) {
            return new JWTGenerateCommand($app['files']);
        });
    }

    /**
     * Helper to get the config values
     * @param string $key
     * @return string
     */
    protected function config($key, $default = null)
    {
        return config("jwt.$key", $default);
    }
}
