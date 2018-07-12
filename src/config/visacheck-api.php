<?php
return [
    // the client environment
    'env' => env('VISACHECK_API_ENV', 'staging'),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | You need to provide the credentials that will be used while communicating
    | with the Visacheck API.
    |
    |
    */
    'client' => [

        // the client ID provided to you for use with your app
        'id' => env('VISACHECK_API_ID', 0),

        // the client secret
        'secret' => env('VISACHECK_API_SECRET', '')
    ]
];