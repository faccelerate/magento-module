# Contract between the module and Faccelerate

Fixed interfaces that the module (this repo) and the Faccelerate API
(`faccelerate/next`) both build against. Change them only here, and in both
places at once.

## Module identity

- Composer package `faccelerate/module-magento`, module `Faccelerate_Connect`,
  PHP namespace `Faccelerate\Connect\`.
- Supports Magento Open Source / Adobe Commerce 2.4.7–2.4.9 and Mage-OS 2.x/3.x,
  PHP 8.2–8.5, Luma. No static assets of its own (no `static-content:deploy`).

## Configuration (Stores → Configuration → Faccelerate)

Section `faccelerate`, all fields store-view scope unless noted.

| Path | Type | Default | Meaning |
| --- | --- | --- | --- |
| `faccelerate/general/enabled` | yes/no | 1 | Master switch for the storefront output. |
| `faccelerate/general/environment` | select `production` / `staging` / `custom` (default scope only) | `production` | Which Faccelerate API to talk to. |
| `faccelerate/general/custom_api_url` | text (default scope only) | empty | Used when environment is `custom`, e.g. `http://host.docker.internal:8787` for local development. |
| `faccelerate/general/cro_key` | text | empty | The workspace's CRO runtime key (`data-fx`). Filled automatically after connecting; editable by hand. |
| `faccelerate/storefront/load_on_checkout` | yes/no | 1 | Also load the CRO loader on checkout pages (`checkout_index_index`). Off for shops whose PCI assessor objects. |
| `faccelerate/storefront/purchase_event` | yes/no | 1 | Push a GA4-style `purchase` event to `window.dataLayer` on the order success page. |
| `faccelerate/connection/*` | (display only) | | The Connect / Disconnect panel, rendered by the frontend model `Faccelerate\Connect\Block\Adminhtml\System\Config\Connection`. |

Internal (not in system.xml, default scope): `faccelerate/connection/integration_id`,
`faccelerate/connection/status` (`none` / `pending` / `connected`),
`faccelerate/connection/workspace_name`, `faccelerate/connection/connect_url`.

API base URLs:

- `production` → `https://api.faccelerate.ai`
- `staging` → `https://api-staging.faccelerate.ai`
- `custom` → the custom URL, without a trailing slash

One class answers all of this: `Faccelerate\Connect\Model\Config`
(`isEnabled(?int $storeId)`, `getApiBaseUrl()`, `getCroKey(?int $storeId)`,
`loadOnCheckout(?int $storeId)`, `purchaseEventEnabled(?int $storeId)`, plus
getters/setters for the internal connection values).

## Storefront output

- Every frontend page (when enabled and a CRO key is set) gets, in the head:
  `<script async src="{apiBase}/x/cro/loader.js" data-fx="{croKey}"></script>`,
  rendered through `SecureHtmlRenderer` so it works under strict CSP. Not on
  checkout pages when `load_on_checkout` is off. Static per store view, so it is
  safe for full-page cache and Varnish.
- CSP: `api.faccelerate.ai` and `api-staging.faccelerate.ai` are whitelisted for
  `script-src`, `connect-src` and `img-src` (`etc/csp_whitelist.xml`). For the
  `custom` environment a dynamic policy collector adds the custom host.
- Order success page: one push, only once per order:
  `window.dataLayer.push({event: "purchase", ecommerce: {transaction_id, value, currency, tax, shipping, items: [{item_id, item_name, price, quantity}]}})`.
  The CRO loader already recognises this shape (`loader-script.ts`).

## Connecting (module ↔ API)

All API calls are HTTPS JSON. The module never needs the merchant to copy a
token.

1. **Connect button (admin).** The module creates (or reuses) a Magento
   integration named `Faccelerate` with exactly these ACL resources:
   `Magento_Backend::store`, `Magento_Config::config`,
   `Magento_Catalog::products`, `Magento_Catalog::categories`,
   `Magento_Sales::sales_order` (incl. actions/view), `Magento_Cms::page`,
   `Magento_Cms::block`. It activates it programmatically (consumer + access
   token), then calls:

   `POST {apiBase}/x/magento/register`
   ```json
   {
     "store_base_url": "https://shop.example.com/",
     "store_name": "Main Website Store",
     "magento_version": "2.4.9",
     "module_version": "0.1.0",
     "consumer_key": "…", "consumer_secret": "…",
     "access_token": "…", "access_token_secret": "…",
     "admin_return_url": "https://shop.example.com/admin/…/faccelerate/"
   }
   ```
   → `200 {"link_id": "…", "connect_url": "https://app.faccelerate.ai/connect/magento?code=…", "expires_at": "ISO-8601"}`.
   The API first checks the credentials with an OAuth1-signed
   `GET {store}/rest/V1/store/storeConfigs`; on failure `422 {"error": "…"}`.
   The module stores status `pending` and the `connect_url`, then redirects the
   admin user's browser to `connect_url`.

2. **Pick the workspace (Faccelerate app).** `/connect/magento?code=…` asks the
   signed-in user which workspace (owner/admin only) gets this store, then binds
   it: a Magento connection with OAuth1 credentials, same side effects as the
   manual Magento connect (platform, SEO project, CRO project). Then it
   redirects to `admin_return_url?faccelerate=connected`. Codes live 30 minutes
   and work once.

3. **Status (module, on the config page and after the redirect).**

   `GET {apiBase}/x/magento/link` (signed, see below)
   → `200 {"status": "pending"|"expired"|"connected"|"disconnected", "workspace_name": "…"|null, "cro_key": "fx…"|null, "environment": "production"|"staging"|"local"}`.
   On `connected` the module saves `cro_key` and `workspace_name`, status
   `connected`.

4. **Disconnect (admin button, and on module uninstall).**

   `POST {apiBase}/x/magento/disconnect` (signed) → `200 {"status": "disconnected"}`.
   The API marks the connection disconnected and deletes the stored
   credentials. The module then deletes the integration (which revokes the
   token) and clears the internal connection values and `cro_key`.

### Request signing for `/link` and `/disconnect`

Headers:
- `X-Faccelerate-Consumer`: the integration's consumer key
- `X-Faccelerate-Timestamp`: Unix seconds
- `X-Faccelerate-Signature`: lowercase hex of
  `HMAC-SHA256(key = consumer_secret, data = METHOD + "\n" + PATH + "\n" + TIMESTAMP)`,
  where `PATH` is the request path without host and query, e.g. `/x/magento/link`.

The API rejects timestamps more than 300 seconds off, and unknown consumers,
with `401`.

## How Faccelerate calls the store

With the four OAuth1 credentials the API signs every Magento REST request
(OAuth 1.0a, `HMAC-SHA256`, `Authorization: OAuth …` header). This works with
Magento's default `oauth/consumer/enable_integration_as_bearer = 0`, so the
merchant never has to change that security setting. Connections made by hand
with only an access token keep using `Authorization: Bearer`.
