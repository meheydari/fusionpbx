# Fonik Add-ons

This FusionPBX application manages the Fonik company and its Click to Call, Popup, Callback, and Gateway synchronization services for the active tenant.

## Installation and access

1. On PostgreSQL installations, run `sql/postgresql/install.sql`. It is transactional, idempotent, and only registers this application. It replaces App Defaults, Menu Defaults, and Permission Defaults for Fonik Add-ons.
2. Set `fonik_addons.api_base_url` and `fonik_addons.admin_api_key` in Default Settings. The base URL should end with `/api/call/`.
3. The feature is disabled for every tenant by default. Edit and run `sql/postgresql/tenant_toggle.sql` to enable or disable exactly one tenant.
4. Tenant administrators receive the Fonik permissions through the standard `admin` group. Remove individual manage permissions from a tenant group if that tenant should have read-only or limited access.
5. Sign out and back in after direct database changes so FusionPBX rebuilds the settings and permission session cache.

For non-superadmin users, both the menu and the page are blocked while `fonik_addons.enabled` is false. Superadmins can always see the page so they can configure and troubleshoot it.

## Architecture decision: tenant-to-company mapping

Status: Accepted.

The active FusionPBX `domain_name` is used as the immutable Fonik company domain. The application lists companies from Fonik and matches only the exact active domain; users cannot submit a `company_id` or another tenant domain. No duplicate local mapping table is maintained.

This keeps the remote Fonik API as the source of truth and prevents cross-tenant selection. The trade-off is one company-list request per page load. If the company list becomes large, a domain-filter endpoint should be added to Fonik before introducing a local cache.

Service forms are unavailable until the tenant company exists. Each service is created with `POST` when absent and updated with `PUT` when present.

## Failure and security behavior

- All mutations require a FusionPBX CSRF token and a specific permission.
- Tenant domain and company ID are derived server-side.
- Redirects are disabled for outbound API requests, and API errors are returned without exposing the admin key.
- A Fonik outage leaves the current configuration unchanged and displays a recoverable error.
