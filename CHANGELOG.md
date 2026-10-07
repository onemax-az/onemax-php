# Changelog

## 0.1.0

First release.

- `OneMax\Client` with `context`, `verifyCode`, `usage`, `confirmUsage`, `voidUsage`, `exchangeCode`,
  `linkStatus`, `verifyLink`, `revokeLink` and `authorizeUrl`.
- `OneMax\Pkce` to generate a verifier and its S256 challenge for account linking.
- Typed results: `Context`, `Offer`, `Verification`, `Usage`, `LinkToken`, `LinkStatus`, with `Reason`
  and `UsageStatus` values that keep a string the library does not know yet.
- An exception for each kind of failure, all under `OneMax\Exception\OneMaxException`.
- A `Transport` interface with a cURL default, so the HTTP layer can be replaced.
