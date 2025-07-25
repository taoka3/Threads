<?php

return [
    'appid' => env('APPID'),
    'apiSecret' => env('APISECRET'),
    'redirectUri' => env('REDIRECT_URI'),
    'endPointUri' => env('END_POINT_URI','https://graph.threads.net/'),
    'version' => env('VERSION','v1.0/'),
];
