<?php

namespace SocialiteProviders\GoogleOneTap\Exceptions;

use Exception;

class DisallowedMethodException extends Exception
{
    protected $message = 'This method is not used for authenticating with Google One Tap';
}
