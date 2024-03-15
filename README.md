# Google One Tap provider for Laravel Socialite

A provider for [Laravel Socialite](https://laravel.com/docs/master/socialite) that allows authentication for Google through Google One Tap. The Google One Tap framework is build on top of OAuth2, but does not use the traditional OAuth authorize user flow. Instead of returning an access token, it returns an authenticating JWT token that expires after an hour.

Google One Tap does not sync with the session of your application, so you should solve this within your application. As long as the credentials of the user aren't revoked and the user is logged in with their Google account or Google Chrome browser, the application will be able to grab a new JWT token with minimal user interaction when necessary.

## Installation

```bash
composer require nickbeen/socialiteproviders-google-one-tap
```

This package depends on `google/apiclient` (including 200+ `google\api-clients-services` packages) and will be also be included when installing this package.

You can run the `google-task-composer-cleanup` script in `composer.json` to only keep the Google API client packages needed for running this Socialite provider. [DO NOT](https://github.com/googleapis/google-api-php-client?tab=readme-ov-file#cleaning-up-unused-services) run this script if your application depends on `google/apiclient`.

## Usage

Please see the [Base Installation Guide](https://socialiteproviders.com/usage/) if Laravel Socialite isn't installed yet into your application.

### Setup Google project

First you might need to create a new project at [Google Cloud console](https://console.cloud.google.com/apis/credentials/consent), set up the *OAuth consent screen* and create a new *OAuth Client ID*. Within the Credentials menu you will find the client ID and client secret which you will need for authenticating.

### Add configuration

You will need to store the client ID and client secret in your `.env` file and add the configuration to `config/services.php`. You will also need to add a redirection url which will be used for logging in and registering with Google One Tap. This package refers to a specific .env value for Google One Tap to avoid any clashes with the standard Google Socialite provider. 

```dotenv
# .env

GOOGLE_CLIENT_ID=314159265-pi.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=mkhYkO_ECIIp11mcZ3CEClhmgh_9FWTV2M_
GOOGLE_LOGIN_URI=/auth/google-one-tap
```

```php
# config/services.php

return [

    // other providers

    'google-one-tap' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_LOGIN_URI'),
    ],
];
```

### Add provider event listener

Configure the package's listener to listen for `SocialiteWasCalled` events. Add the event to your `listen[]` array in `app/Providers/EventServiceProvider`. See the [Base Installation Guide](https://socialiteproviders.com/usage/) for detailed instructions.

```php
// app/Providers/EventServiceProvider

protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        // other providers
        \SocialiteProviders\GoogleOneTap\GoogleOneTapExtendSocialite::class,
    ],
];
```

## Usage

Google One Tap requires a specific implementation both in the front-end as the back-end.

### Front-end

On every page where you want to use Google One Tap, you will need to include the following script in the header of your html templates.

```html
@guest
    <script src="https://accounts.google.com/gsi/client" async defer></script>
@endguest
```

The actual Google One Tap prompt can be initiated with either javascript or html. The following code handles the response server side in html. It does not matter where you place this code. You can also append `data-client_id` and `data-login_uri` to any existing html element. Check [references](#references) for more settings and variations such as a full javascript implementation.

```html
@guest
    <div id="g_id_onload"
         data-auto_select="true"
         data-client_id="{{ config('services.google-one-tap.client_id') }}"
         data-login_uri="{{ config('services.google-one-tap.redirect') }}"
         data-use_fedcm_for_prompt="true">
    </div>
@endguest
```

Styling this element won't have any effect since Google One tap is migrating to [FedCM](https://developer.chrome.com/en/docs/privacy-sandbox/fedcm/) which means the prompt will be handled by the browser itself if the browser supports it.

For signing out you should add a `g_id_signout` class to your sign-out button to avoid a redirection loop because of `data-auto_select` in the previous snippet.

```html
<form action="{{ route('logout') }}" method="post">
    @csrf
    <button class="g_id_signout">Sign out</button>
</form>
```

Google One Tap has a cooldown period when a user closes the Google One Tap prompt. The more often a user closes the prompt, the longer it will take for the prompt to be able to reappear to the user. Therefore, you need to include a sign-in button for a fallback to a Google Sign-In prompt. You will likely only want to include this button on login and register pages. [Only](https://developers.google.com/identity/gsi/web/reference/html-reference#button-attribute-types) the data-type field is required.

```html
<div class="g_id_signin"
    data-type="standard">
</div>
```

### Back-end

Google One Tap is build on top of OAuth, but works different with an authenticating JTW token instead of with access tokens and refresh tokens. The `redirect()` and `refreshToken()` method won't be used in this context and will throw a `DisallowedMethodException` as a reminder.

Your controller won't need to redirect the user and instead of resolving the user, you can immediately resolve the token.

```php
use Laravel\Socialite\Facades\Socialite;

return Socialite::driver('google-one-tap')->userFromToken($token);
```

This method will return the payload of the JWT token or throw an `InvalidIdTokenException` if the provided token was invalid.

#### Payload array

| Field | Type | Description |
| --- | --- | --- |
| avatar | ?string | The user's profile picture if present |
| email | string | The user's email address |
| email_verified | boolean | True, if Google has verified the email address |
| host_domain | ?string | The host domain of the user's GSuite email address if present |
| id | string | The user's unique Google ID |
| name | string | The user's name |

Only use `id` field as identifier for the user as it is unique among all Google Accounts and never reused. Don't use `email` as an identifier because a Google Account can have multiple email addresses at different points in time.

Using the `email`, `email_verified` and `host_domain` fields you can determine if Google hosts and is authoritative for an email address. In cases where Google is authoritative the user is confirmed to be the legitimate account owner.

#### Handling the payload

With the payload containing the `id` you can now handle the user flow after the user finished interacting with the Google One Tap prompt. This usually involves either registering the user if the Google ID isn't present in your database or logging in the user if you have a user registered with this Google ID.

Optionally you can use `email` to check if the user already has a user account or Socialite credentials from another provider, and possibly connect the accounts or notify the user account. In basic Laravel code it would look something like this:

```php
// routes/web.php

use App\Controllers\Auth\GoogleOneTapController;
use Illuminate\Support\Facades\Route;

Route::post('auth/google-one-tap', [GoogleOneTapController::class, 'handler'])
    ->middleware('guest')
    ->name('google-one-tap.handler');
```

```php
// e.g. GoogleOneTapController.php

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\GoogleOneTap\Exceptions\InvalidIdTokenException;

public function handler(Request $request)
{
    // Verify and validate JWT received from Google One Tap prompt
    try {
        $googleUser = Socialite::driver('google-one-tap')->userFromToken($request->input('credential'));
    } catch (InvalidIdTokenException $exception) {
        return response()->json(['error' => $exception])
    }

    // Log the user in if the Google ID is associated with a user
    if ($googleUser = User::where('google_id', $googleUser['id'])->first()) {
        auth()->login($googleUser);
    }
    
    // Send user to registration form to provide missing details like username
    return redirect()->view('register.google-one-tap', compact('googleUser'))
}
```

## FAQ

### How can I use authoritative scopes with Google One Tap to e.g. upload to Google Drive?

Google One Tap can only be used for authentication (who you are). For authorization, you need to use the built-in Google provider of Laravel Socialite. Both providers can be used simultaneously to give you the best of both worlds.

### Can I check if a user logged in with One Tap, used an existing session, etc.?

The `select_by` field in the response from Google contains [several possible values](https://developers.google.com/identity/gsi/web/reference/html-reference#select_by) like `auto`, `user` and `user_1tap` that indicate how the user interacted with your application when signing up or signing in. In Laravel the value can be easily accessed in your controller.

```php
$select_by = request()->input('select_by')
```

## References

- https://piraces.dev/posts/how-to-use-google-one-tap/
- https://developers.google.com/identity/gsi/web/guides/overview
- https://developers.google.com/identity/gsi/web/reference/html-reference
- https://developers.google.com/identity/gsi/web/reference/js-reference
- https://github.com/googleapis/google-api-php-client
- https://googleapis.github.io/google-api-php-client/main/

## License

This package is licensed under the MIT License (MIT). See the [LICENSE](LICENSE.md) for more details.
