<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class Fonnte
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function send(String $phoneNumber, String $message)
    {
        $bodyResource = [
            "target" => $phoneNumber,
            "message" => $message,
        ];
        $client = new Client();
        $url = "https://api.fonnte.com/send";
        $fonnteResponse = $client->request('POST', $url, [
            'headers' => [
                'Accept' => 'Application/json',
                'Authorization' => config('services.fonnte_token'),
                'Content-Type' => 'Application/json',
            ],
            'body' => json_encode($bodyResource),
        ]);
        return $fonnteResponse;
    }
}
