<?php

namespace Visacheck\Visacheck\LaravelAuth\Auth;



use Illuminate\Auth\GenericUser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Notifications\Notifiable;
use JsonSerializable;
use Visacheck\Visacheck\Sdk;

class VisacheckUser extends GenericUser implements Arrayable, JsonSerializable
{
    use Notifiable;

    /** @var Sdk  */
    private $sdk;

    /**
     * DorcasUser constructor.
     *
     * @param array    $attributes
     * @param Sdk|null $sdk
     */
    public function __construct(array $attributes = [], Sdk $sdk = null)
    {
        parent::__construct($attributes);
        $this->sdk = $sdk ?: Container::getInstance()->make(Sdk::class);
    }

    /**
     * Sets the attributes on the resource.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Returns the Dorcas Sdk in use by the instance.
     *
     * @return Sdk
     */
    public function getSdk(): ?Sdk
    {
        return $this->sdk;
    }

    /**
     * Returns the company information, if available.
     *
     * @param bool $requestIfNotAvailable request the information from the API if it's not available
     * @param bool $asObject
     *
     * @return array|null|object
     */
    public function company(bool $requestIfNotAvailable = true, bool $asObject = false)
    {
        if (!array_key_exists('company', $this->attributes) && $requestIfNotAvailable) {
            $service = $this->sdk->createProfileService();
            $response = $service->addQueryArgument('include', 'company')->send('get');
            # make a request to the API
            if (!$response->isSuccessful()) {
                return null;
            }
            $this->attributes = $response->getData();
        }
        $company = $this->attributes['company']['data'] ?? [];
        return $asObject ? (object) $company : $company;
    }

    /**
     * @inheritdoc
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    /**
     * @return string
     */
    public function routeNotificationForSms()
    {
        return (string) $this->attributes['phone'] ?? '';
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}