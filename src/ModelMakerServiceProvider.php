<?php namespace AwkwardIdeas\ModelMaker;

use Illuminate\Support\ServiceProvider;
use AwkwardIdeas\MyPDO\MyPDOServiceProvider;

class ModelMakerServiceProvider extends ServiceProvider
{

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/modelmaker.php';

        $this->publishes([$configPath => $this->getConfigPath()], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(__DIR__ . '/../config/modelmaker.php', 'modelmaker');

        $this->app['modelmaker.clean'] = $this->app->share(function () {
            return new Commands\ModelMakerClean();
        });

        $this->app['modelmaker.generate'] = $this->app->share(function () {
            return new Commands\ModelMakerGenerate();
        });

        $this->commands(
            'modelmaker.clean',
            'modelmaker.generate'
        );

        $this->app->register(\AwkwardIdeas\MyPDO\MyPDOServiceProvider::class);
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    private function getConfigPath()
    {
        return config_path('modelmaker.php');
    }
}
