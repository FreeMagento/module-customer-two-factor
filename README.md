# FreeMagento_CustomerTwoFactor

Two-factor authentication for the **storefront** customer login (Magento's built-in 2FA only covers the admin).
After a correct password the customer is emailed a 6-digit one-time code and must enter it before they are signed in.

Hyvä-native (Alpine.js + Tailwind, no RequireJS/jQuery). Tested against Magento Open Source 2.4.8 / Hyvä 1.5.

## What it does

- **Login interception** – plugin around `customer/account/loginPost`. Password is checked with the same
  `AccountManagement::authenticate()` call core uses (lockout counters, email-confirmation and captcha all still apply);
  the login is then parked in the session, a code is emailed and the customer is redirected to `/twofactor/verify`.
- **Verify page** – `/twofactor/verify`: numeric code input (auto-submits on 6 digits, paste-friendly, `autocomplete="one-time-code"`),
  resend with cooldown countdown, "use a different account".
- **Enforcement modes** – *Optional* (customers opt in under My Account › Login Security) or *Required* (everyone).
- **Trusted devices** – "Remember this device for N days": HttpOnly, SameSite=Lax cookie `fm_2fa_device`; only an HMAC of the
  token is stored. Customers see and revoke devices under Login Security. Turning 2FA off forgets all devices.
- **Code security** – codes are HMAC-hashed with the installation crypt key, expire (default 10 min), are single-use and are
  discarded after N wrong attempts (default 5). Resend is rate-limited (default 60 s). One active code per customer.
- **Admin** – Stores › Configuration › Customers › Two-Factor Authentication. Staff can also toggle the flag per customer on the
  admin customer form (attribute `fm_twofactor_enabled`).
- **Housekeeping** – daily cron `freemagento_twofactor_cleanup` removes expired codes and devices.

## Install

```
bin/magento module:enable FreeMagento_CustomerTwoFactor
bin/magento setup:upgrade
bin/magento cache:flush
```

Or without Composer: unpack into `app/code/FreeMagento/CustomerTwoFactor` and run the same commands (app/code install).

Then rebuild the Hyvä theme CSS so the module's Tailwind classes are included (add the module's `view/frontend` folder
to the theme's `@source` list if the theme does not scan `app/code` already).

## Configuration

| Path | Default | Notes |
|---|---|---|
| `freemagento_twofactor/general/enabled` | 1 | Master switch (website scope) |
| `freemagento_twofactor/general/mode` | optional | `optional` or `required` |
| `freemagento_twofactor/code/lifetime` | 10 | minutes |
| `freemagento_twofactor/code/max_attempts` | 5 | wrong entries before a new code is needed |
| `freemagento_twofactor/code/resend_cooldown` | 60 | seconds |
| `freemagento_twofactor/code/email_template` | freemagento_twofactor_code | |
| `freemagento_twofactor/code/email_identity` | general | sender |
| `freemagento_twofactor/trusted_device/enabled` | 1 | |
| `freemagento_twofactor/trusted_device/lifetime` | 30 | days |
| `freemagento_twofactor/trusted_device/checked_by_default` | 1 | |

## Theming

Templates use standard Hyvä classes (`form-input`, `btn btn-primary`, `card`, `text-secondary`). A theme can restyle without
overriding templates by setting block arguments in `twofactor_verify_index.xml` / `twofactor_account_index.xml`:
`section_css_classes`, `title_css_classes`, `card_css_classes`, `heading_css_classes`, `muted_css_classes`.

Email: `view/frontend/email/code.html` (vars: `code`, `code_spaced`, `lifetime`, `customer_name`, `customer_firstname`, `store`).

## Routes

| Route | Method | Purpose |
|---|---|---|
| `twofactor/verify` | GET | code entry page (only while a login is pending) |
| `twofactor/verify/post` | POST | verify code, complete login |
| `twofactor/verify/resend` | POST | send a fresh code |
| `twofactor/verify/cancel` | POST | abandon pending login |
| `twofactor/account` | GET | My Account › Login Security (logged in) |
| `twofactor/account/save` | POST | opt in / out (optional mode only) |
| `twofactor/account/revoke` | POST | forget one (`id`) or all (`all=1`) devices |

## Not covered (yet)

- AJAX login (`customer/ajax/login`, used by Luma's login popup) is not intercepted; Hyvä does not use it.
- Only the email method. TOTP/authenticator-app support would slot in as a second `OtpService` implementation.
- GraphQL `generateCustomerToken` / REST token endpoints are untouched (headless storefronts).

## Licence

MIT — free to use on any store, including client work. Issues and pull requests: https://github.com/freemagento/module-customer-two-factor

Made by [FreeMagento](https://freemagento.co.uk), a Mentalworkz Ltd project.
