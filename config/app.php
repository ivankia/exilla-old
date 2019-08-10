<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => 'Exilla',

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value determines the "version" your application is currently running
    | in. You may want to follow the "Semantic Versioning" - Given a version
    | number MAJOR.MINOR.PATCH when an update happens: https://semver.org.
    |
    */

    'version' => app('git.version'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services your application utilizes. Should be true in production.
    |
    */

    'production' => false,

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],


    'orderbook_params' => [
            'column'       => 'size_price', // default column
            'sortL'        => SORT_DESC,
            'sortR'        => SORT_DESC,
            'headers'      => ['Buy Size', ' Buy Price', '  ', 'Sell Price', 'Sell Size'], // table heads
            'price_limits' => [10400, 10960],
            'min_size'     => 10,
            'limit'        => 75,

            'exchange'     => 'bitmex',

            'bitmex' => [
                'api_url'            => 'http://localhost:4444/orderBookL2?symbol=XBTUSD&depth=',
                'depth'              => 0,
                'symbol'             => 'XBTUSD',
                'api_url_instrument' => 'http://localhost:4444/instrument?symbol=',
            ],

            //'schema'        => 'quoter_average',

            'schema'       => 'discrete_levels',
            'discrete_levels' => [
                'low'  => 100,
                'mid'  => 200,
                'high' => 300
            ],
    ],
];
