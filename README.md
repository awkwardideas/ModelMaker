# ModelMaker: MySQL to Laravel Model Generatoror

[![Latest Stable Version](https://poser.pugx.org/awkwardideas/modelmaker/v/stable)](https://packagist.org/packages/awkwardideas/modelmaker) 
[![Total Downloads](https://poser.pugx.org/awkwardideas/modelmaker/downloads)](https://packagist.org/packages/awkwardideas/modelmaker) 
[![Latest Unstable Version](https://poser.pugx.org/awkwardideas/modelmaker/v/unstable)](https://packagist.org/packages/awkwardideas/modelmaker) 
[![License](https://poser.pugx.org/awkwardideas/modelmaker/license)](https://packagist.org/packages/awkwardideas/modelmaker)

## Install Via Composer

composer require awkwardideas/modelmaker

## Add to Laravel App Config

    /*
     * Package Service Providers...
     */ 
    AwkwardIdeas\ModelMaker\ModelMakerServiceProvider::class,
    //

## Commands via Artisan

Command line actions are done via artisan.  The host, username, password from the .env file are used for making the connection.

### php artisan modelmaker:clean

Removes all model maker files from the app/models folder

Options:

--force  Bypass confirmations

### php artisan modelmaker:generate

Create migration files using the database information in .env

Options:

--from=  Database to migrate from
--force  Bypass confirmations