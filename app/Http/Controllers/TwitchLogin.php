<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Minicli\Curly\Client;

class TwitchLogin extends Controller
{
    public function index(Request $request)
    {
        $client_id = env('TWITCH_CLIENT_ID');
        $client_secret = env('TWITCH_CLIENT_SECRET');
        $redirect_uri = 'http://localhost:8000/login/twitch';
        $login_url = 'https://id.twitch.tv/oauth2/authorize';

        $state = $request->query('state');

        if ($state === null) {
            $state = md5(time());
            $auth_url = sprintf(
                '%s?response_type=code&client_id=%s&redirect_uri=%s&state=%s&scope=%s',
                $login_url,
                $client_id,
                $redirect_uri,
                $state,
                "channel:read:subscriptions"
            );

            return redirect($auth_url);
        }

        $code = $request->query('code');
        $token_url = 'https://id.twitch.tv/oauth2/token';
        $curly = new Client();

        $response = $curly->post(sprintf(
            '%s?code=%s&client_id=%s&client_secret=%s&grant_type=authorization_code&redirect_uri=%s',
            $token_url,
            $code,
            $client_id,
            $client_secret,
            $redirect_uri
        ), [], ['Accept:', 'application/json']);
        

        if ($response['code'] == 200) {
            $token_response = json_decode($response['body'],1);

            $access_token = $token_response['access_token'];

            $user_info = $this->getCurrentUser($curly, $client_id, $access_token);

            $info = Http::get('https://api.twitch.tv/kraken/user',
                                $this->getHeaders($client_id, $access_token)
                            );
            
            $user = new Client();
            $response = $user->get(
                'https://api.twitch.tv/kraken/user',
                [
                    'Accept: application/json',
                    "Client-ID: $client_id",
                    "Authorization: OAuth $access_token"
                ]
            );                            
            
            return response()->json($response);
            

        } else {
            echo "ERROR.";
            print_r($response);
        }
    }

    public function getCurrentUser(Client $client, $client_id, $access_token)
    {
        $response = $client->get(
            'https://id.twitch.tv/oauth2/validate',
            $this->getHeaders($client_id, $access_token)
        );

        if ($response['code'] == 200) {
            return json_decode($response['body'], 1);
        }

        return null;
    }

    public function getHeaders($client_id, $access_token)
    {
        return [
            "Client-ID: $client_id",
            "Authorization: Bearer $access_token"
        ];
    }    
}
