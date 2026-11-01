# Faccelerate for Magento

`Faccelerate_Connect` connects your Magento store to [Faccelerate](https://faccelerate.ai) in one click. After that, the Faccelerate agents can test and improve your shop: CRO experiments on the storefront and better product texts.

Composer package: `faccelerate/module-magento`. License: OSL-3.0.

## What it does

- **One-click connect.** One button creates a Magento integration with a fixed list of permissions and links the store to your Faccelerate workspace. You never copy an API token.
- **CRO loader on every page.** A small script (`/x/cro/loader.js`) is added to the head of every storefront page, including the checkout. It is the same for every visitor, so it works with the full page cache and Varnish. It follows your Content Security Policy: the Faccelerate hosts are whitelisted for `script-src`, `connect-src` and `img-src`.
- **"Not on checkout" switch.** One setting keeps the loader off the checkout pages. See [PCI DSS](#pci-dss-643).
- **Purchase event.** On the order success page the module pushes a GA4-style `purchase` event to `window.dataLayer`, once per order.
- **CLI commands.** `bin/magento faccelerate:connect`, `faccelerate:status` and `faccelerate:disconnect`.

## Requirements

| | |
| --- | --- |
| Magento Open Source / Adobe Commerce | 2.4.7 to 2.4.9 |
| Mage-OS | 2.x and 3.x |
| PHP | 8.2 to 8.5 |
| Theme | Luma tested. Hyvä is not verified yet. |

The module has no static assets, so you do not need `setup:static-content:deploy`.

## Install

Run these commands in your Magento root:

```bash
composer require faccelerate/module-magento
bin/magento module:enable Faccelerate_Connect
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

## Connect

In the admin: **Stores → Configuration → Faccelerate → Connection → Connect to Faccelerate**.

1. Click the button. The module creates the integration and registers your store.
2. You land in Faccelerate. Sign in and pick the workspace for this store (owner or admin of that workspace).
3. You return to the Magento admin. The status shows **connected** and the CRO key is filled in.

If you are not returned, click **Check status**.

On the command line:

```bash
bin/magento faccelerate:connect     # prints a link: open it and pick the workspace
bin/magento faccelerate:status      # shows the status and finishes the connection
```

The link works once and expires after 30 minutes.

## Settings

All under **Stores → Configuration → Faccelerate**.

| Setting | Default | What it does |
| --- | --- | --- |
| Enabled | Yes | Master switch for everything the module adds to the storefront. |
| CRO runtime key | filled by connect | Without a key, nothing is added to the storefront. |
| Load on checkout pages | Yes | Turn off to keep the loader off the checkout. |
| Send purchase event | Yes | The `purchase` event on the order success page. |
| Faccelerate environment | Production | Leave it on Production unless Faccelerate support says otherwise. |

## Update

```bash
composer update faccelerate/module-magento
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

## Uninstall

Option 1. Let Magento do it:

```bash
bin/magento module:uninstall --remove-data Faccelerate_Connect
```

This tells Faccelerate that the store is gone, deletes the integration and its token, removes all `faccelerate/*` settings, and removes the package with Composer.

Option 2. Disconnect first, then remove the package yourself:

```bash
bin/magento faccelerate:disconnect
composer remove faccelerate/module-magento
bin/magento setup:upgrade
bin/magento cache:flush
```

You can also click **Disconnect** in the admin instead of the CLI command.

## PCI DSS 6.4.3

The CRO loader is a third-party script. On payment pages, PCI DSS 6.4.3 asks you to keep an inventory of such scripts, to justify each one, and to check their integrity.

Faccelerate is in your script inventory once you install this module. If your assessor does not accept a third-party script on the checkout, switch it off there: **Load on checkout pages = No**. The loader then stays on all other pages. The purchase event on the order success page does not need the loader.

This is not legal or compliance advice. Ask your assessor.

## What Faccelerate reads and writes

Connecting creates one Magento integration named `Faccelerate` with these ACL resources, and no others:

- `Magento_Backend::store` and `Magento_Config::config`: read store and configuration data
- `Magento_Catalog::products` and `Magento_Catalog::categories`: read the catalog
- `Magento_Sales::sales_order` (with actions and view): read orders
- `Magento_Cms::page` and `Magento_Cms::block`: read CMS pages and blocks

Faccelerate reads this data to measure your shop and to write product texts. It writes only product texts that you or your team approved in Faccelerate. It does not change prices, stock, orders, customers or settings.

Faccelerate signs its requests with OAuth 1.0a. You do not need to change `oauth/consumer/enable_integration_as_bearer`. You can see and delete the integration under **System → Extensions → Integrations**. Deleting it revokes access at once.

## Privacy

- The CRO loader sets a first-party cookie, `fx_uid`, to recognise a returning visitor in an experiment.
- The purchase event holds the order id, totals (value, tax, shipping, currency) and, per item, the id, name, price and quantity. It holds no name, email, address or other personal data.
- Check with your legal team whether the loader and its cookie need to be in your cookie banner.

## Support

Questions or problems: **support@faccelerate.ai** (placeholder: replace with the final support address before the first release).

Security issues: see [SECURITY.md](SECURITY.md).

## License

[OSL-3.0](LICENSE).
