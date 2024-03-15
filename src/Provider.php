<?php

namespace SocialiteProviders\GoogleOneTap;

use Google\Client;
use Illuminate\Support\Arr;
use SocialiteProviders\GoogleOneTap\Exceptions\DisallowedMethodException;
use SocialiteProviders\GoogleOneTap\Exceptions\InvalidIdTokenException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'GOOGLE-ONE-TAP';

    /**
     * @throws DisallowedMethodException
     */
    public function redirect()
    {
        throw new DisallowedMethodException();
    }

    /**
     * @throws DisallowedMethodException
     */
    public function refreshToken($refreshToken)
    {
        throw new DisallowedMethodException();
    }

    protected function getAuthUrl($state)
    {
        //
    }

    protected function getTokenUrl()
    {
        //
    }

    /**
     * @throws InvalidIdTokenException
     */
    protected function getUserByToken($token): array
    {
        /** Don't utilize session state */
        $this->stateless = true;

        /** Initiate the Google API client */
        $client = new Client([
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        /** Verifies the JWT signature, the aud claim, the exp claim and the iss claim */
        $payload = $client->verifyIdToken($token);

        if (! $payload) {
            throw new InvalidIdTokenException();
        }

        return $payload;
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            'avatar' => Arr::get($user, 'picture'),
            'email' => Arr::get($user, 'email'),
            'email_verified' => Arr::get($user, 'email_verified'),
            'host_domain' => Arr::get($user, 'hd'),
            'id' => Arr::get($user, 'sub'),
            'name' => Arr::get($user, 'name'),
        ]);
    }
}
