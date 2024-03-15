<?php

namespace SocialiteProviders\GoogleOneTap;

use SocialiteProviders\Manager\SocialiteWasCalled;

class GoogleOneTapExtendSocialite
{
    /**
     * Register the provider.
     *
     * @param SocialiteWasCalled $socialiteWasCalled
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('google-one-tap', Provider::class);
    }
}
