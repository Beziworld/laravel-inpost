<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | ShipX credentials
    |--------------------------------------------------------------------------
    |
    | API token and organization id from Manager Paczek (My account > API).
    | The sandbox uses separate credentials from sandbox-manager.paczkomaty.pl.
    |
    */

    'token' => env('INPOST_TOKEN'),

    'organization_id' => env('INPOST_ORGANIZATION_ID'),

    'sandbox' => (bool) env('INPOST_SANDBOX', true),

    'timeout' => (int) env('INPOST_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Geowidget
    |--------------------------------------------------------------------------
    |
    | Public token of the parcel locker map (Manager Paczek > API > Geowidget),
    | bound to the domain of the shop.
    |
    */

    'geowidget_token' => env('INPOST_GEOWIDGET_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | ShipX webhooks are not signed; requests are accepted only from the InPost
    | address range. Register the URL of the route in Manager Paczek; InPost
    | checks it with a GET request that must answer 200.
    |
    */

    'webhooks' => [
        'enabled' => (bool) env('INPOST_WEBHOOKS_ENABLED', true),
        'path' => env('INPOST_WEBHOOK_PATH', 'inpost/webhook'),
        'name' => 'inpost.webhook',
        'middleware' => ['api'],
        'allowed_ips' => ['91.216.25.0/24'],
    ],

];
