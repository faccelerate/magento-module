# Changelog

All notable changes to this module are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-01

First release.

### Added

- One-click connect between a Magento store and a Faccelerate workspace, from
  **Stores → Configuration → Faccelerate** or with `bin/magento faccelerate:connect`.
  The module creates a Magento integration with a fixed list of ACL resources and
  registers it with Faccelerate. No token has to be copied.
- `faccelerate:connect`, `faccelerate:status` and `faccelerate:disconnect` commands.
- The Faccelerate CRO loader on every storefront page, rendered through
  `SecureHtmlRenderer`, safe for full page cache and Varnish.
- Content Security Policy whitelist for the Faccelerate API hosts, and a dynamic
  policy for a custom development URL.
- Setting to keep the loader off the checkout pages (PCI DSS 6.4.3).
- GA4-style `purchase` event in `window.dataLayer` on the order success page, once
  per order.
- Clean-up on `module:uninstall --remove-data`: disconnects from Faccelerate,
  deletes the integration and removes all `faccelerate/*` settings.

### Supported

- Magento Open Source / Adobe Commerce 2.4.7 to 2.4.9, Mage-OS 2.x and 3.x,
  PHP 8.2 to 8.5. Luma tested; Hyvä not verified yet.
