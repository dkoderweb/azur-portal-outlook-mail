<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\TokenStore\TokenCache;
use Illuminate\Http\Request;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class AuthController extends Controller
{
  public function signin()
  {
    // Generate the code verifier
    $codeVerifier = bin2hex(random_bytes(64)); // You can adjust the length as needed
    
    // Generate the code challenge (hashed version of the verifier)
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    // Save code verifier to session for later use in token request
    session(['oauthCodeVerifier' => $codeVerifier]);

    // Initialize the OAuth client
    $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => config('azure.appId'),
      'clientSecret'            => config('azure.appSecret'),
      'redirectUri'             => config('azure.redirectUri'),
      'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
      'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
      'urlResourceOwnerDetails' => '',
      'scopes'                  => config('azure.scopes')
    ]);

    // Add code_challenge and code_challenge_method to the authorization URL
    $authUrl = $oauthClient->getAuthorizationUrl([
      'code_challenge' => $codeChallenge,
      'code_challenge_method' => 'S256'
    ]);

    // Save client state so we can validate in callback
    session(['oauthState' => $oauthClient->getState()]);

    // Redirect to AAD signin page
    return redirect()->away($authUrl);
  }


  public function callback(Request $request)
  {
    // Validate state
    $expectedState = session('oauthState');
    $request->session()->forget('oauthState');
    $providedState = $request->query('state');

    if (!isset($expectedState)) {
      return redirect('/');
    }

    if (!isset($providedState) || $expectedState != $providedState) {
      return redirect('/')
        ->with('error', 'Invalid auth state')
        ->with('errorDetail', 'The provided auth state did not match the expected value');
    }

    // Authorization code should be in the "code" query param
    $authCode = $request->query('code');
    if (isset($authCode)) {
      // Initialize the OAuth client
      $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => config('azure.appId'),
        'clientSecret'            => config('azure.appSecret'),
        'redirectUri'             => config('azure.redirectUri'),
        'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
        'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
        'urlResourceOwnerDetails' => '',
        'scopes'                  => config('azure.scopes')
      ]);

      try {
        // Retrieve the code verifier from session
        $codeVerifier = session('oauthCodeVerifier');
        $request->session()->forget('oauthCodeVerifier');

        // Make the token request with the code verifier
        $accessToken = $oauthClient->getAccessToken('authorization_code', [
          'code' => $authCode,
          'code_verifier' => $codeVerifier // Pass the code verifier here
        ]);

        $graph = new Graph();
        $graph->setAccessToken($accessToken->getToken());

        $user = $graph->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
          ->setReturnType(Model\User::class)
          ->execute();

        $tokenCache = new TokenCache();
        $tokenCache->storeTokens($accessToken, $user);

        return redirect('/');
      }
      catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        return redirect('/')
          ->with('error', 'Error requesting access token')
          ->with('errorDetail', json_encode($e->getResponseBody()));
      }
    }

    return redirect('/')
      ->with('error', $request->query('error'))
      ->with('errorDetail', $request->query('error_description'));
  }


  public function signout()
  {
    $tokenCache = new TokenCache();
    $tokenCache->clearTokens();
    return redirect('/');
  }
}

// APP_NAME=Laravel
// APP_ENV=local
// APP_KEY=base64:NbPoUNSWt9j2je79aheBJH+20aaksB01RH8yKs8pFWw=
// APP_DEBUG=true
// APP_URL=http://localhost

// LOG_CHANNEL=stack
// LOG_DEPRECATIONS_CHANNEL=null
// LOG_LEVEL=debug

// DB_CONNECTION=mysql
// DB_HOST=127.0.0.1
// DB_PORT=3306
// DB_DATABASE=laravel
// DB_USERNAME=root
// DB_PASSWORD=

// BROADCAST_DRIVER=log
// CACHE_DRIVER=file
// FILESYSTEM_DISK=local
// QUEUE_CONNECTION=sync
// SESSION_DRIVER=file
// SESSION_LIFETIME=120

// MEMCACHED_HOST=127.0.0.1

// REDIS_HOST=127.0.0.1
// REDIS_PASSWORD=null
// REDIS_PORT=6379

// MAIL_MAILER=smtp
// MAIL_HOST=mailhog
// MAIL_PORT=1025
// MAIL_USERNAME=null
// MAIL_PASSWORD=null
// MAIL_ENCRYPTION=null
// MAIL_FROM_ADDRESS="hello@example.com"
// MAIL_FROM_NAME="${APP_NAME}"

// AWS_ACCESS_KEY_ID=
// AWS_SECRET_ACCESS_KEY=
// AWS_DEFAULT_REGION=us-east-1
// AWS_BUCKET=
// AWS_USE_PATH_STYLE_ENDPOINT=false

// PUSHER_APP_ID=
// PUSHER_APP_KEY=
// PUSHER_APP_SECRET=
// PUSHER_HOST=
// PUSHER_PORT=443
// PUSHER_SCHEME=https
// PUSHER_APP_CLUSTER=mt1

// VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
// VITE_PUSHER_HOST="${PUSHER_HOST}"
// VITE_PUSHER_PORT="${PUSHER_PORT}"
// VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
// VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

// OAUTH_APP_ID=1f51d30d-8453-4153-b241-09de0715ef89
// OAUTH_APP_SECRET='2Su8Q~
// sSWkU2dea
// _nbxWtqixli6J9hSSiDpIbdky'
// // OAUTH_REDIRECT_URI=https://8db3-27-109-24-46.ngrok-free.app/callback
// // OAUTH_SCOPES='openid profile offline_access user.read mailboxsettings.read calendars.readwrite'
// // OAUTH_AUTHORITY=https://login.microsoftonline.com/57f01261-d7db-4887-8390-18d685c0be52
// // OAUTH_AUTHORIZE_ENDPOINT=/oauth2/v2.0/authorize
// // OAUTH_TOKEN_ENDPOINT=/oauth2/v2.0/token

