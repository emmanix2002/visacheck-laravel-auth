<?php

namespace Visacheck\Visacheck\LaravelAuth\Auth;


use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Visacheck\Visacheck\Sdk;
use Visacheck\Visacheck\VisacheckResponse;

class VisacheckUserProvider implements UserProvider
{
    /** @var Sdk  */
    private $sdk;

    /** @var array */
    private $config;

    /**
     * DorcasUserProvider constructor.
     *
     * @param Sdk        $sdk
     * @param array|null $config
     */
    public function __construct(Sdk $sdk, array $config = null)
    {
        $this->sdk = $sdk;
        $this->config = $config ?: [];
    }

    /**
     * Returns the Dorcas SDK instance in use by the provider.
     *
     * @return Sdk
     */
    public function getSdk(): Sdk
    {
        return $this->sdk;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed $identifier
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $apiAuthToken = Cache::get('visacheck.auth_token.'.$identifier, null);
        if (!empty($apiAuthToken)) {
            $this->sdk->setAuthorizationToken($apiAuthToken);
        }
        $resource = $this->sdk->createUserResource($identifier);
        $response = $resource->relationships('company')->send('get');
        if (!$response->isSuccessful()) {
            return null;
        }
        return new VisacheckUser($response->getData(), $this->sdk);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string $token
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $apiAuthToken = Cache::get('visacheck.auth_token.'.$identifier, null);
        if (!empty($apiAuthToken)) {
            $this->sdk->setAuthorizationToken($apiAuthToken);
        }
        $resource = $this->sdk->createUserResource($token);
        $response = $resource->relationships('company')
                                ->addQueryArgument('using_column', 'remember_token')
                                ->send('get');
        if (!$response->isSuccessful()) {
            return null;
        }
        return new VisacheckUser($response->getData(), $this->sdk);
    }
    
    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string                                     $token
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $apiAuthToken = Cache::get('visacheck.auth_token.'.$user->getAuthIdentifier(), null);
        if (!empty($apiAuthToken)) {
            $this->sdk->setAuthorizationToken($apiAuthToken);
        }
        $service = $this->sdk->createProfileService();
        $service->addBodyParam('remember_token', $token)->send('put');
    }
    
    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function retrieveByCredentials(array $credentials)
    {
        $token = login_via_password($this->sdk, $credentials['email'] ?? '', $credentials['password'] ?? '');
        # we get the authentication token
        if ($token instanceof VisacheckResponse) {
            return null;
        }
        $this->sdk->setAuthorizationToken($token);
        # set the authorization token
        $service = $this->sdk->createProfileService();
        $response = $service->addQueryArgument('include', 'company')->send('get');
        if (!$response->isSuccessful()) {
            return null;
        }
        $user = $response->getData();
        # get the actual user data
        $lifetimeMinutes = 24 * 60;
        # the lifetime for the cache store, and cookie store
        Cookie::queue('visacheck_store_id', $user['id'], $lifetimeMinutes);
        # set the user id cookie
        Cache::put('visacheck.auth_token.'.$user['id'], $token, $lifetimeMinutes);
        # save the auth token to the cache
        return new VisacheckUser($user, $this->sdk);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  array                                      $credentials
     *
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];
        return Hash::check($plain, $user->getAuthPassword());
    }
}