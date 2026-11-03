# Changelog

All notable changes to this module are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-11-03

### Added
- GA4 funnel events through the store's Google tag: `view_item` on product pages, `add_to_cart` after an AJAX add
  to cart, and `begin_checkout` on the checkout. Magento's own Google tag sends page views and purchases only, so
  GA4 had no middle of the funnel. Each event waits for the Google tag's `config` before it is sent. Switch them off
  with **Send GA4 funnel events = No**. Needs Luma or a Luma-based theme (jQuery and RequireJS); Hyvä is not
  covered yet.

## [0.1.1] - 2026-11-02

### Fixed
- Connect, Check status and Disconnect on Stores → Configuration → Faccelerate did nothing but save the
  configuration: the panel's own forms sat inside the configuration page's form, which browsers drop. The
  buttons now open a small confirm page with its own form. Found while connecting luma.mediya.dev.

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
