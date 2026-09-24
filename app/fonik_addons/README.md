# Fonik Add-ons

This FusionPBX application manages Fonik services for the active tenant. Each service has an independent page, menu item, permission, and configurable documentation URL.

## Menu and permissions

The parent menu is `افزودنی‌ها`. It is visible only when `fonik_addons.enabled` is true for the active domain and the signed-in user can access at least one child page.

| Page | Permission | Scope |
|---|---|---|
| تنظیمات شرکت | `fonik_company_settings` | Global `superadmin` only |
| کلیک برای تماس | `fonik_click_to_call_manage` | Granted per domain |
| پاپ‌آپ تماس | `fonik_popup_manage` | Granted per domain |
| تماس بازگشتی | `fonik_callback_manage` | Granted per domain |
| همگام‌سازی درگاه‌ها | `fonik_gateway_sync` | Granted per domain |

Service permissions are deliberately not granted to the global `admin` or `superadmin` groups. Use `sql/postgresql/tenant_permissions.sql` to select the modules available to a group in one tenant. Sign out and back in after changing permissions.

## Installation

1. Run `sql/postgresql/install.sql`. It is transactional, idempotent, and scoped to Fonik rows. Do not run FusionPBX App Defaults, Menu Defaults, or Permission Defaults.
2. Configure the shared `api_base_url` and `admin_api_key` values in Default Settings under `fonik_addons`, or use `sql/postgresql/configure.sql`.
3. Enable exactly one tenant with `sql/postgresql/tenant_toggle.sql`.
4. Grant that tenant's module permissions with `sql/postgresql/tenant_permissions.sql`.
5. Sign out and back in so FusionPBX rebuilds settings and permissions in the session.

## Documentation links

Set these shared HTTP(S) URLs in Default Settings. A page hides its documentation button while its URL is empty or invalid.

- `company_documentation_url`
- `click_to_call_documentation_url`
- `popup_documentation_url`
- `callback_documentation_url`
- `gateway_sync_documentation_url`

## Tenant-to-company mapping

The active FusionPBX `domain_name` is the immutable Fonik company domain. The application lists companies from Fonik and matches only the exact active domain; users cannot submit a company ID or another tenant domain. The Fonik API remains the source of truth and no duplicate local mapping table is maintained.

Only an authorized superadmin can create or update a company. Service pages remain unavailable until the active tenant company exists.

## Security behavior

- The tenant feature flag applies to all users, including superadmins.
- Every page validates its own permission; hiding a menu item is not treated as authorization.
- All mutations require a FusionPBX CSRF token.
- Tenant domain and company ID are derived server-side.
- API redirects are disabled and the admin API key is never included in user-facing errors.
- Documentation links accept only valid HTTP(S) URLs.
