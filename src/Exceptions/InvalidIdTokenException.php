<?php

namespace SocialiteProviders\GoogleOneTap\Exceptions;

use Exception;

class InvalidIdTokenException extends Exception
{
    protected $message = 'The provided id_token is invalid';
}
