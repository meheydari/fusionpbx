# راهنمای استقرار و تست افزودنی‌های Fonik

این راهنما برای استقرار امن ماژول Fonik Add-ons روی FusionPBX، فعال‌سازی یک tenant آزمایشی و انجام تست end-to-end نوشته شده است.

نسخه مبنا:

- Branch: `5.1`
- Commit: نسخه تأییدشده تیم فنی
- Database: PostgreSQL
- مسیر معمول پروژه: `/var/www/fusionpbx`

در این فرایند نباید App Defaults، Menu Defaults یا Permission Defaults عمومی FusionPBX اجرا شوند. اسکریپت‌های SQL فقط رکوردهای همین ماژول را ثبت می‌کنند.

## ۱. اطلاعات موردنیاز پیش از شروع

تیم استقرار باید این مقادیر را از تیم فنی دریافت کند:

| متغیر | نمونه | توضیح |
|---|---|---|
| `APP_DIR` | `/var/www/fusionpbx` | مسیر نصب FusionPBX |
| `DB_NAME` | `fusionpbx` | نام دیتابیس PostgreSQL |
| `FONIK_BASE_URL` | `http://172.22.100.20:8000/api/call/` | آدرس API که حتماً به `/api/call/` ختم شود |
| `FONIK_ADMIN_API_KEY` | محرمانه | مقدار هدر `ADMIN-API-KEY` |
| `TENANT_DOMAIN` | `test.example.com` | مقدار دقیق `domain_name` مربوط به tenant آزمایشی |
| `TEST_COMPANY_NAME` | `Test Company` | نام شرکت آزمایشی در Fonik |

پیش‌نیازهای ضروری:

- دیتابیس سرور PostgreSQL باشد. اگر مقدار `database.0.type` برابر `pgsql` نیست، این SQLها اجرا نشوند.
- IP خروجی سرور FusionPBX در Fonik whitelist شده باشد.
- سرور FusionPBX به `FONIK_BASE_URL` دسترسی شبکه داشته باشد.
- افزونه PHP cURL فعال باشد.
- ابزارهای `git`، `curl`، `jq`، `psql`، `pg_dump` و `pg_restore` روی سرور موجود باشند.
- tenant آزمایشی از قبل در جدول `v_domains` وجود داشته باشد.
- کلید Admin API در تیکت، لاگ، پیام عمومی یا Git ثبت نشود.

## ۲. بررسی وضعیت فعلی و تهیه backup

ابتدا وارد سرور شوید و متغیرهای غیرمحرمانه را تنظیم کنید:

```bash
export APP_DIR=/var/www/fusionpbx
export DB_NAME=fusionpbx
export BACKUP_DIR=/var/backups/postgresql
export FONIK_BASE_URL=http://172.22.100.20:8000/api/call/
export TENANT_DOMAIN=test.example.com
```

برای کلید API از ورودی مخفی استفاده کنید تا مقدار آن در history شل ذخیره نشود:

```bash
read -rsp "Fonik ADMIN API key: " FONIK_ADMIN_API_KEY
export FONIK_ADMIN_API_KEY
echo
```

وضعیت repository باید قبل از deploy پاک باشد:

```bash
cd "$APP_DIR"
git status --short
git branch --show-current
```

اگر `git status --short` خروجی داشت، عملیات متوقف شود تا تغییرات محلی بررسی شوند.

از دیتابیس backup بگیرید:

```bash
sudo install -d -o postgres -g postgres -m 700 "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/fusionpbx-before-fonik-$(date +%Y%m%d-%H%M%S).dump"
sudo -u postgres pg_dump --format=custom --file="$BACKUP_FILE" "$DB_NAME"
sudo -u postgres pg_restore --list "$BACKUP_FILE" | head
```

معیار قبولی:

- دستور `pg_dump` بدون خطا تمام شود.
- فایل backup خالی نباشد.
- دستور `pg_restore --list` فهرست اشیای دیتابیس را نمایش دهد.

## ۳. استقرار کد

کد تأییدشده را دریافت کنید:

```bash
cd "$APP_DIR"
git fetch origin
git checkout 5.1
git pull --ff-only origin 5.1
git rev-parse HEAD
```

خروجی دستور آخر باید این commit یا نسخه‌ای جدیدتر که این commit را در history دارد نشان دهد:

```text
4c3b9017e...
```

وجود فایل‌های اصلی را بررسی کنید:

```bash
test -f "$APP_DIR/app/fonik_addons/fonik_addons.php"
test -f "$APP_DIR/app/fonik_addons/company.php"
test -f "$APP_DIR/app/fonik_addons/click_to_call.php"
test -f "$APP_DIR/app/fonik_addons/popup.php"
test -f "$APP_DIR/app/fonik_addons/callback.php"
test -f "$APP_DIR/app/fonik_addons/gateway_sync.php"
test -f "$APP_DIR/app/fonik_addons/sql/postgresql/install.sql"
test -f "$APP_DIR/resources/classes/menu.php"
```

مالکیت و permission فایل‌های جدید باید با سایر پوشه‌های `app` یکسان باشد. از اجرای `chmod -R 777` خودداری شود.

## ۴. بررسی ارتباط با Fonik قبل از تغییر دیتابیس

ابتدا اتصال TCP/HTTP و اعتبار کلید را کنترل کنید:

```bash
curl --silent --show-error --fail-with-body \
  --connect-timeout 5 \
  --max-time 15 \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  --header "Accept: application/json" \
  "${FONIK_BASE_URL}company/"
```

نتیجه مورد انتظار:

- HTTP `200`
- پاسخ JSON شامل آرایه شرکت‌ها، حتی اگر آرایه خالی باشد.

خطاهای متداول:

- HTTP `403`: کلید API یا IP whitelist اشتباه است.
- Timeout/Connection refused: route، firewall یا آدرس API بررسی شود.
- HTML به‌جای JSON: مسیر `FONIK_BASE_URL` یا reverse proxy اشتباه است.

تا زمانی که این مرحله موفق نشده، migration اجرا نشود.

## ۵. نصب محدود رکوردهای ماژول در دیتابیس

اسکریپت نصب transactional و idempotent است:

```bash
cd "$APP_DIR"
sudo -u postgres psql \
  --set ON_ERROR_STOP=1 \
  --dbname "$DB_NAME" \
  --file app/fonik_addons/sql/postgresql/install.sql
```

خروجی نهایی باید این موارد را نشان دهد:

- `fonik_permission_count = 5`
- `fonik_default_setting_count = 10`
- برای هر منوی FusionPBX تعداد `fonik_menu_item_count = 6`

کنترل مستقل:

```bash
sudo -u postgres psql --dbname "$DB_NAME" --command "
SELECT COUNT(*) AS permissions
FROM v_permissions
WHERE application_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf';

SELECT default_setting_subcategory, default_setting_value, default_setting_enabled
FROM v_default_settings
WHERE default_setting_category = 'fonik_addons'
ORDER BY default_setting_order;
"
```

در این مرحله مقدار `fonik_addons.enabled` در تنظیمات پیش‌فرض باید `false` باشد؛ بنابراین هیچ tenant عادی هنوز دسترسی ندارد.

## ۶. تنظیم آدرس API و کلید محرمانه

فایل اصلی repository ویرایش نشود. یک کپی موقت با دسترسی محدود بسازید:

```bash
install -m 600 \
  "$APP_DIR/app/fonik_addons/sql/postgresql/configure.sql" \
  /tmp/fonik-configure.sql
```

در `/tmp/fonik-configure.sql` فقط این دو مقدار را جایگزین کنید:

```sql
target_api_base_url text := 'http://172.22.100.20:8000/api/call/';
target_admin_api_key text := 'REAL-ADMIN-API-KEY';
```

سپس اجرا کنید:

```bash
sudo -u postgres psql \
  --set ON_ERROR_STOP=1 \
  --dbname "$DB_NAME" \
  --file /tmp/fonik-configure.sql
```

خروجی باید `api_base_url` و وضعیت `[configured]` برای کلید را نمایش دهد و نباید خود کلید را چاپ کند.

بعد از اجرای موفق، فایل موقت حذف شود:

```bash
rm -f /tmp/fonik-configure.sql
unset FONIK_ADMIN_API_KEY
```

در صورت آماده بودن فایل‌های مستندات، آدرس HTTP(S) آن‌ها را در بخش Default Settings و دسته `fonik_addons` وارد کنید:

- `company_documentation_url`
- `click_to_call_documentation_url`
- `popup_documentation_url`
- `callback_documentation_url`
- `gateway_sync_documentation_url`

خالی بودن هر مقدار فقط دکمه «دانلود مستندات» همان صفحه را پنهان می‌کند.

## ۷. فعال‌سازی فقط tenant آزمایشی

ابتدا وجود tenant را کنترل کنید:

```bash
sudo -u postgres psql --dbname "$DB_NAME" \
  --command "SELECT domain_uuid, domain_name FROM v_domains WHERE domain_name = '$TENANT_DOMAIN';"
```

باید دقیقاً یک ردیف برگردد. اگر صفر یا بیش از یک ردیف بود، عملیات متوقف شود.

از فایل toggle یک نسخه موقت بسازید:

```bash
install -m 600 \
  "$APP_DIR/app/fonik_addons/sql/postgresql/tenant_toggle.sql" \
  /tmp/fonik-tenant-toggle.sql
```

در فایل موقت مقادیر زیر را تنظیم کنید:

```sql
target_domain_name text := 'test.example.com';
target_enabled boolean := TRUE;
```

سپس اجرا کنید:

```bash
sudo -u postgres psql \
  --set ON_ERROR_STOP=1 \
  --dbname "$DB_NAME" \
  --file /tmp/fonik-tenant-toggle.sql

rm -f /tmp/fonik-tenant-toggle.sql
```

کنترل نتیجه:

```bash
sudo -u postgres psql --dbname "$DB_NAME" --command "
SELECT d.domain_name, s.domain_setting_value, s.domain_setting_enabled
FROM v_domain_settings s
JOIN v_domains d ON d.domain_uuid = s.domain_uuid
WHERE d.domain_name = '$TENANT_DOMAIN'
  AND s.domain_setting_category = 'fonik_addons'
  AND s.domain_setting_subcategory = 'enabled';
"
```

مقدار مورد انتظار `true` است.

## ۸. تخصیص دسترسی ماژول‌ها به tenant

فعال‌سازی قابلیت به‌تنهایی هیچ‌یک از چهار سرویس را به مدیر tenant نمی‌دهد. یک کپی موقت از فایل permission بسازید:

```bash
install -m 600 \
  "$APP_DIR/app/fonik_addons/sql/postgresql/tenant_permissions.sql" \
  /tmp/fonik-tenant-permissions.sql
```

در فایل موقت نام tenant، گروه و وضعیت هر ماژول را تنظیم کنید:

```sql
target_domain_name text := 'test.example.com';
target_group_name text := 'admin';
allow_click_to_call boolean := TRUE;
allow_popup boolean := TRUE;
allow_callback boolean := TRUE;
allow_gateway_sync boolean := FALSE;
```

سپس اجرا و فایل موقت را حذف کنید:

```bash
sudo -u postgres psql \
  --set ON_ERROR_STOP=1 \
  --dbname "$DB_NAME" \
  --file /tmp/fonik-tenant-permissions.sql

rm -f /tmp/fonik-tenant-permissions.sql
```

صفحه «تنظیمات شرکت» مستقل از این چهار permission است و فقط به `superadmin` نمایش داده می‌شود.

## ۹. بازسازی cache اجرایی

کاربران تست باید از FusionPBX خارج و دوباره وارد شوند. این کار تنظیمات دامنه و permissionها را در session بازسازی می‌کند.

اگر production با OPcache و `validate_timestamps=0` اجرا می‌شود، PHP-FPM به‌صورت reload و نه restart بازخوانی شود. نام service با سیستم‌عامل و نسخه PHP متفاوت است:

```bash
systemctl list-units --type=service | grep -E 'php.*fpm'
sudo systemctl reload php8.2-fpm
```

در دستور دوم نام واقعی service سرور جایگزین شود.

## ۱۰. تست دسترسی tenant

### تست با superadmin

1. با superadmin وارد شوید.
2. tenant آزمایشی را انتخاب کنید.
3. منوی «افزودنی‌ها» و زیرمنوی «تنظیمات شرکت» باید دیده شوند.
4. زیرمنوهای سرویس فقط در صورت داشتن permission همان tenant نمایش داده شوند.

### تست با مدیر tenant

1. با کاربری از گروه استاندارد `admin` همان tenant وارد شوید.
2. منوی «افزودنی‌ها» فقط در صورت داشتن حداقل یک permission سرویس باید دیده شود.
3. زیرمنوی «تنظیمات شرکت» نباید دیده شود و مسیر مستقیم آن باید HTTP `403` بدهد:

```text
/app/fonik_addons/company.php
```

4. کاربر tenant دیگری که قابلیت برای او فعال نشده نباید منو را ببیند.
5. بازکردن مستقیم URL برای tenant غیرفعال باید HTTP `403` بدهد.

## ۱۱. ایجاد شرکت آزمایشی

با `superadmin` وارد زیرمنوی «افزودنی‌ها ← تنظیمات شرکت» شوید:

- نام شرکت: مقدار `TEST_COMPANY_NAME`
- دامنه: باید خودکار و read-only برابر `TENANT_DOMAIN` باشد.
- فعال باشد: برای تست اولیه می‌تواند روشن باشد.

روی «ایجاد شرکت» کلیک کنید.

نتیجه مورد انتظار:

- پیام موفقیت نمایش داده شود.
- شناسه شرکت نمایش داده شود.
- نام و دامنه read-only شوند.
- سایر سرویس‌ها در همین صفحه نمایش داده نشوند.

شرکت را مستقیماً از API نیز کنترل کنید. ابتدا کلید را دوباره به‌صورت مخفی دریافت کنید:

```bash
read -rsp "Fonik ADMIN API key: " FONIK_ADMIN_API_KEY
export FONIK_ADMIN_API_KEY
echo

COMPANY_JSON="$(curl --silent --show-error --fail-with-body \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  "${FONIK_BASE_URL}company/")"

printf '%s' "$COMPANY_JSON" | jq --arg domain "$TENANT_DOMAIN" \
  '[.[] | select(.domain == $domain)]'
```

باید دقیقاً یک شرکت با دامنه tenant دیده شود. شناسه را استخراج کنید:

```bash
export COMPANY_ID="$(printf '%s' "$COMPANY_JSON" | jq -r \
  --arg domain "$TENANT_DOMAIN" \
  '.[] | select(.domain == $domain) | .id')"

test -n "$COMPANY_ID"
echo "Fonik company id: $COMPANY_ID"
```

## ۱۲. تست سرویس‌ها با کمترین اثر عملیاتی

برای تست اولیه، سرویس‌ها را ایجاد ولی غیرفعال نگه دارید تا تماس یا Notification واقعی تولید نشود.

### Click to Call

در فرم Click to Call:

- سرویس فعال باشد: خاموش
- ضبط تماس فعال باشد: خاموش

ذخیره کنید. سپس کنترل API:

```bash
curl --silent --show-error --fail-with-body \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  "${FONIK_BASE_URL}company/${COMPANY_ID}/click-to-call-service/" | jq
```

مقادیر مورد انتظار:

```json
{
  "enabled": false,
  "has_record": false
}
```

### Callback

در فرم Callback:

- سرویس فعال باشد: خاموش
- تماس ورودی، خروجی و بدون پاسخ: خاموش
- بازه بررسی: ۳۰ دقیقه

کنترل API:

```bash
curl --silent --show-error --fail-with-body \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  "${FONIK_BASE_URL}company/${COMPANY_ID}/callback-service/" | jq
```

مقدار `callback_check_range` باید `30` باشد.

### Popup

یک endpoint آزمایشی کنترل‌شده انتخاب کنید. در فرم Popup:

- سرویس فعال باشد: خاموش
- نوع: `API`
- آدرس API: endpoint آزمایشی HTTPS
- کلید اختصاصی Popup: مقدار غیرproduction
- نام فیلد کلید: `apikey`
- جهت تماس: `BOTH`
- متد: `POST`
- حداقل دو فیلد، مثلاً شماره خارجی و داخلی، فعال باشند.
- labelها، مثلاً `number` و `extension`، تکمیل شوند.

کنترل API:

```bash
curl --silent --show-error --fail-with-body \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  "${FONIK_BASE_URL}company/${COMPANY_ID}/popup-service/" | jq
```

مقدار `enabled` باید `false` باشد و ساختار `fields` باید حداقل دو فیلد فعال با label متناظر داشته باشد.

### Gateway Sync

این عملیات داده‌های Gateway را با سیستم مرکزی همگام می‌کند. فقط بعد از تأیید تیم تلفنی اجرا شود.

در UI روی «شروع همگام‌سازی» کلیک کنید. پیام موفقیت باید نمایش داده شود.

کنترل مستقیم API در صورت نیاز:

```bash
curl --silent --show-error --fail-with-body \
  --request PUT \
  --header "ADMIN-API-KEY: $FONIK_ADMIN_API_KEY" \
  "${FONIK_BASE_URL}company/${COMPANY_ID}/sync-gateways/" | jq
```

## ۱۳. بررسی لاگ‌ها

در صورت خطا این منابع بررسی شوند:

- لاگ PHP-FPM
- error log وب‌سرور
- لاگ application یا syslog سرور
- لاگ reverse proxy سرویس Fonik

خطاهای ارتباطی ماژول با prefix زیر ثبت می‌شوند:

```text
[fonik_addons]
```

کلید Admin API نباید در هیچ‌یک از لاگ‌ها دیده شود.

## ۱۴. معیار پذیرش نهایی

- [ ] commit موردنظر روی سرور deploy شده است.
- [ ] backup دیتابیس تهیه و قابل خواندن است.
- [ ] تست `GET company/` از خود سرور HTTP 200 می‌دهد.
- [ ] تعداد permissionهای Fonik برابر ۵ است.
- [ ] تعداد default settingهای Fonik برابر ۱۰ است.
- [ ] هر منوی FusionPBX شامل یک والد و پنج زیرمنوی Fonik است.
- [ ] قابلیت فقط برای tenant آزمایشی فعال شده است.
- [ ] tenant غیرفعال منو را نمی‌بیند و URL مستقیم 403 است.
- [ ] tenant فعال فقط زیرمنوهای دارای permission را می‌بیند.
- [ ] تنظیمات شرکت فقط برای superadmin قابل مشاهده است.
- [ ] شرکت با دامنه دقیق tenant ایجاد شده و فقط یک رکورد دارد.
- [ ] سه سرویس در حالت غیرفعال ایجاد و از API قابل دریافت هستند.
- [ ] Gateway Sync فقط با تأیید تیم تلفنی تست شده است.
- [ ] هیچ کلید واقعی در فایل repository، history شل یا لاگ باقی نمانده است.

## ۱۵. غیرفعال‌سازی tenant یا rollback

برای قطع فوری دسترسی یک tenant، `tenant_toggle.sql` را با مقدار زیر اجرا کنید:

```sql
target_enabled boolean := FALSE;
```

سپس کاربران آن tenant logout/login کنند. منو پنهان و URL مستقیم مسدود می‌شود؛ اطلاعات شرکت و سرویس‌ها در Fonik حذف نمی‌شوند.

برای rollback کامل ثبت دیتابیسی ماژول:

```bash
cd "$APP_DIR"
sudo -u postgres psql \
  --set ON_ERROR_STOP=1 \
  --dbname "$DB_NAME" \
  --file app/fonik_addons/sql/postgresql/uninstall.sql
```

این rollback فقط رکوردهای ثبت‌شده برای Fonik Add-ons را حذف می‌کند. حذف فایل‌های برنامه یا revert کد باید جداگانه و مطابق فرایند release انجام شود. بازیابی کامل backup فقط در صورت خرابی گسترده دیتابیس و با تأیید مسئول دیتابیس انجام شود.
