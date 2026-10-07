# OneMax for PHP

OneMax is a 1+1 membership club in Baku. Members pay for a plan and show a code at the counter of a
partner venue to use an offer. This library lets a partner's own system (a till, a website, an
ordering app) check a member and record a usage through the OneMax partner API.

## Install

```bash
composer require onemax-az/onemax
```

PHP 8.1 or newer with the `curl` and `json` extensions. The library has no other dependencies.

## Getting a key

1. OneMax switches API access on for your partner account.
2. The owner creates a key in the partner panel under API.
3. A key belongs to one venue. Create one key for each venue you integrate.
4. Keep the key on your server. Never ship it in a browser or a mobile app.

Keys start with `omx_live_`.

```php
use OneMax\Client;

$onemax = new Client($_ENV['ONEMAX_API_KEY']);
```

The constructor also takes `baseUrl`, `siteUrl`, `timeout` (seconds, 10 by default) and `transport`:

```php
$onemax = new Client(
    apiKey: $_ENV['ONEMAX_API_KEY'],
    baseUrl: 'https://api.onemax.az/v1',
    siteUrl: 'https://onemax.az',
    timeout: 5.0,
);
```

## Quick start: a code

The member reads the six digit code from their OneMax card, or you scan the QR text. `verifyCode`
answers yes or no. On yes it also opens a pending usage, which counts only once you confirm it.

```php
$context = $onemax->context();
$offerId = $context->offers[0]->id;

$verification = $onemax->verifyCode('123456', $offerId);

if ($verification->usable) {
    $usage = $onemax->confirmUsage($verification->usageId, 'order-42');
} else {
    echo 'Not usable: ' . $verification->reason->value;
}
```

Pass your own order id as the reference. Confirming again with the same reference is safe and
returns the same usage, so you can repeat the call after a network failure.

If the order falls through, release the pending usage:

```php
$onemax->voidUsage($verification->usageId);
```

Read a usage at any time with `$onemax->usage($usageId)`. It has `usageId`, `status`, `reference`,
`createdAt`, `confirmedAt` and `voidedAt`. The dates are `DateTimeImmutable` values. `status` is a
`UsageStatus` with `isPending()`, `isConfirmed()` and `isVoided()`.

## Account linking

A member can link their OneMax account to their account in your system once, so they do not need to
type a code each time. You never learn the member's name, email, phone or photo. You only hold a
link token.

### 1. Send the member to the permission page

Create a PKCE pair and keep the verifier and a random state in the member's session.

```php
use OneMax\Pkce;

$pkce = Pkce::generate();
$state = bin2hex(random_bytes(16));

$_SESSION['onemax_verifier'] = $pkce->verifier;
$_SESSION['onemax_state'] = $state;

$url = $onemax->authorizeUrl(
    clientId: 'omx_client_your_id',
    redirectUri: 'https://shop.example/onemax/callback',
    codeChallenge: $pkce->challenge,
    state: $state,
    locale: 'en',
);

header('Location: ' . $url);
```

`locale` is `az`, `ru` or `en`. Leave it out for Azerbaijani. `authorizeUrl` makes no request.
`Pkce::challengeFor($verifier)` gives the challenge for a verifier you already hold.

### 2. Exchange the code

The member allows and comes back to your redirect address with `code` and `state`, or with
`error=access_denied` when they refuse.

```php
if (($_GET['error'] ?? null) === 'access_denied') {
    exit('The member did not allow the link.');
}
if (hash_equals($_SESSION['onemax_state'], (string) ($_GET['state'] ?? '')) === false) {
    exit('The state does not match.');
}

$link = $onemax->exchangeCode(
    code: (string) $_GET['code'],
    codeVerifier: $_SESSION['onemax_verifier'],
    redirectUri: 'https://shop.example/onemax/callback',
);

saveLinkToken($memberId, $link->linkToken);
```

Send the same redirect address you used for the permission page. Store the link token (it starts
with `omx_link_`) on your server next to your own member record.

### 3. Check later

```php
$status = $onemax->linkStatus($linkToken);

if ($status->linked && $status->active) {
    echo 'This member has OneMax.';
}

$verification = $onemax->verifyLink($linkToken, $offerId);

if ($verification->usable) {
    $onemax->confirmUsage($verification->usageId, 'order-43');
}
```

`linkStatus` only answers yes or no. `verifyLink` answers yes or no and, on yes, opens a pending
usage exactly like `verifyCode`. When the member has removed the link, `linkStatus` answers
`linked: false` and `verifyLink` answers the reason `not_linked`. Neither is an error.

To remove a link from your side:

```php
$onemax->revokeLink($linkToken);
```

## Results

`verifyCode` and `verifyLink` return a `Verification`:

| Property | Type | Meaning |
| --- | --- | --- |
| `active` | `bool` | The member has a subscription that works today. |
| `usable` | `bool` | The member may use the offer right now. |
| `reason` | `Reason` | Why the answer is what it is. |
| `usageId` | `?string` | Set when `usable` is true. Confirm it or void it. |
| `dailyLimit` | `int` | The member's daily limit at this venue. |

`reason->value` is the raw string. The known values are constants on `OneMax\Result\Reason`:

| Value | Constant | Predicate | Meaning |
| --- | --- | --- | --- |
| `ok` | `Reason::OK` | `isOk()` | The member may use the offer. |
| `not_subscribed` | `Reason::NOT_SUBSCRIBED` | `isNotSubscribed()` | No subscription that works today. |
| `limit_reached` | `Reason::LIMIT_REACHED` | `isLimitReached()` | The daily limit at this venue is used up. |
| `offer_unavailable` | `Reason::OFFER_UNAVAILABLE` | `isOfferUnavailable()` | The offer is not open right now. |
| `invalid_code` | `Reason::INVALID_CODE` | `isInvalidCode()` | The code is not valid. |
| `not_linked` | `Reason::NOT_LINKED` | `isNotLinked()` | The link token no longer points to a member. |

A value the library does not know yet is kept as it is. `isKnown()` tells you whether it is one of
the above, and `is('some_value')` compares against any string. `UsageStatus` works the same way.

## Errors

Every failure the API or the network produces is an `OneMax\Exception\OneMaxException`.

| Exception | When |
| --- | --- |
| `TransportException` | No usable answer: a connection failure, a timeout, or a body that is not the expected JSON. |
| `ApiException` | The API answered with an error. The classes below extend it. |
| `AuthenticationException` | 401. The key is not valid. |
| `AccessDisabledException` | 403. API access is switched off for the partner. |
| `NotFoundException` | 404. The usage does not exist. |
| `ConflictException` | 409. For example `usage_limit_reached`, `too_late`, `reference_used`, `reference_mismatch`. |
| `InvalidRequestException` | 400 and 422. For example `offer_required`, `unknown_offer`, `link_code_invalid`. |
| `RateLimitException` | 429. `retryAfter` holds the seconds to wait, when the API gives them. |
| `ServerException` | 500 and above. |

An `ApiException` has `status`, `errorCode`, `details` and `requestId`. Its message is the API's
Azerbaijani text and is meant for logs. Decide what to do from `errorCode`, and word your own
message for the member. The class is chosen by HTTP status, so a new code on a known status still
arrives as the same class.

```php
use OneMax\Exception\ApiException;
use OneMax\Exception\ConflictException;
use OneMax\Exception\RateLimitException;
use OneMax\Exception\TransportException;

try {
    $usage = $onemax->confirmUsage($usageId, $orderId);
} catch (ConflictException $exception) {
    if ($exception->errorCode === 'reference_used') {
        cancelOrder($orderId);
    }
} catch (RateLimitException $exception) {
    sleep($exception->retryAfter ?? 1);
} catch (ApiException $exception) {
    error_log(sprintf(
        'OneMax %d %s %s (request %s)',
        $exception->status,
        $exception->errorCode,
        $exception->getMessage(),
        $exception->requestId ?? 'unknown',
    ));
} catch (TransportException $exception) {
    error_log('OneMax did not answer: ' . $exception->getMessage());
}
```

An empty API key and an unknown locale are mistakes in the calling code, so they throw PHP's own
`InvalidArgumentException` before any request is made.

## Limits

- The member's daily limit applies exactly as it does at a counter. A usage counts when it is
  confirmed.
- A key may make 120 requests a minute. Beyond that the API answers 429.
- The library never retries on its own. `confirmUsage` with a reference is safe to retry. The other
  calls are yours to repeat when it makes sense.

## Replacing the HTTP layer

The client talks through `OneMax\Http\Transport`, one method that takes a `Request` and returns a
`Response`. The default is `CurlTransport`. Pass your own to use another HTTP client or to fake the
API in your tests.

```php
use OneMax\Client;
use OneMax\Http\Request;
use OneMax\Http\Response;
use OneMax\Http\Transport;

final class FixedTransport implements Transport
{
    public function send(Request $request): Response
    {
        return new Response(200, '{"linked":true,"active":true}');
    }
}

$onemax = new Client('omx_live_test', transport: new FixedTransport());
```

A transport that cannot get an answer should throw `OneMax\Exception\TransportException`.

## Development

```bash
composer install
composer format:check
composer analyse
composer test
```

`composer format` rewrites the files to the PSR-12 style. `composer analyse` runs PHPStan at level
max, with the one rule that asks for array value types in doc blocks switched off. The tests need no network: they use a fake transport, plus a local PHP server on `127.0.0.1`
for the cURL transport.

The package is registered on Packagist once. After that a new version appears there when a version
tag such as `v0.1.0` is pushed to the repository.

## License

MIT. See [LICENSE](LICENSE).
