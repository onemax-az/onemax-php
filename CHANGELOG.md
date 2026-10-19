# Changelog

## 0.2.0

- Test mode. A key that starts with `omx_test_` works against OneMax test members on the same
  address, so an integration can be built and tried before a real member is involved.
- `Client::mode()` says whether the client holds a live or a test key, as a `Mode`.
- `Context`, `Verification`, `Usage`, `LinkToken` and `LinkStatus` carry `mode`, the mode the API
  answered in. It is `null` when talking to a server that does not send it.

## 0.1.0

First release.

- `OneMax\Client` with `context`, `verifyCode`, `usage`, `confirmUsage`, `voidUsage`, `exchangeCode`,
  `linkStatus`, `verifyLink`, `revokeLink` and `authorizeUrl`.
- `OneMax\Pkce` to generate a verifier and its S256 challenge for account linking.
- Typed results: `Context`, `Offer`, `Verification`, `Usage`, `LinkToken`, `LinkStatus`, with `Reason`
  and `UsageStatus` values that keep a string the library does not know yet.
- An exception for each kind of failure, all under `OneMax\Exception\OneMaxException`.
- A `Transport` interface with a cURL default, so the HTTP layer can be replaced.
