# Academics — InfinityFree / cPanel Deployment Guide

یہ guide موجودہ Laravel نہیں بلکہ **PHP + PDO + MySQL** Academics application کے لیے ہے۔ Production deployment سے پہلے database backup اور migrations لازمی ہیں۔

## 1. Hosting اور database تیار کریں

1. Hosting panel میں domain اور MySQL database بنائیں۔
2. MySQL host، database name، username اور password محفوظ رکھیں۔
3. اگر database پہلے سے استعمال ہو رہا ہے تو phpMyAdmin سے مکمل SQL backup لیں۔
4. Existing database پر `database.sql` کو دوبارہ import نہ کریں: یہ legacy full dump ہے اور اس میں `DROP TABLE` statements موجود ہیں۔

## 2. Production environment configure کریں

Project root میں `.env.example` کی copy بنا کر اسے `.env` نام دیں۔ اصل credentials صرف server کے `.env` میں رکھیں؛ GitHub پر commit نہ کریں۔

کم از کم یہ values درست کریں:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_DOMAIN=your-domain.example
APP_URL=https://your-domain.example

DB_HOST=your-hosting-mysql-host
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

MAIL_HOST=smtp.your-email-provider.example
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.example
MAIL_FROM_NAME="Academics"
```

- `APP_URL` مکمل canonical URL ہو، آخر میں slash نہ بھی ہو تو ٹھیک ہے۔
- Production میں `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` خالی نہ چھوڑیں۔ Configuration غائب ہونے پر application محفوظ طریقے سے 503 دے گی، localhost/root پر fallback نہیں کرے گی۔
- SMTP settings password-reset email کے لیے استعمال ہوتی ہیں۔ SMTP credentials نہ ہوں تو صرف hosting کا PHP `mail()` transport fallback کے طور پر آزمایا جائے گا۔
- `.htaccess` میں `.env`, `config/`, `includes/`, `database/`, `storage/` اور `tests/` کی direct web access block ہے۔ اسے remove نہ کریں۔

## 3. Database migrations چلائیں

موجودہ schema version کے لیے migrations `database/migrations/` میں ترتیب وار `0001` سے `0013` تک ہیں۔

**اگر hosting پر PHP CLI/Terminal دستیاب ہے:**

1. پہلے database backup لیں۔
2. Project root سے یہ command چلائیں:

```bash
php database/migrate.php up
```

3. Migration output میں ہر migration کے successful ہونے کی تصدیق کریں۔ Runner `schema_migrations` میں applied version اور checksum محفوظ کرتا ہے۔ FTP workflow versioned `database/migrations/*.sql` files اور runner کو deploy کرتا ہے، اس لیے hosting Terminal دستیاب ہو تو یہ command server پر بھی چل سکتی ہے۔
4. Primary runner SaaS compatibility provisioning بھی چلاتا ہے۔ Migration `0013` موجودہ legacy schools کے لیے ایک سال کی migration-grace subscription بناتی ہے، صرف ان schools کے لیے جن کی subscription history موجود نہیں؛ دوبارہ چلانے پر duplicate نہیں بنتی۔

4. Fresh installation پر پہلے platform administrator بنائیں:

```bash
php database/create_super_admin.php
```

یہ CLI-only tool strong password مانگتا ہے۔ Migration اب کوئی public default super-admin password create نہیں کرتی۔ اگر hosting پر CLI دستیاب نہیں تو local PHP CLI سے password hash generate کریں اور phpMyAdmin میں صرف اپنے secure admin email/hash کے ساتھ `users` میں `role='super_admin'`, `school_id=NULL`, `is_active=1` insert کریں۔ Plain-text password کو SQL file یا Git میں نہ رکھیں۔

**اگر hosting پر CLI دستیاب نہیں اور صرف phpMyAdmin ہے:**

1. Database select کریں۔
2. `database/migrations/` کی SQL files کو filename order میں ایک ایک کر کے import کریں: `0001`, `0002` ... `0013`۔
3. کسی migration میں error آئے تو آگے والی files import نہ کریں؛ پہلے error resolve کریں۔
4. Manual import کے بعد CLI migration runner خود سے نہ چلائیں جب تک `schema_migrations` میں applied versions/checksums درست طور پر record نہ کر دیے جائیں؛ ورنہ runner پرانی migrations دوبارہ چلانے کی کوشش کر سکتا ہے۔

`database/seed.sql` صرف local/demo data کے لیے ہے۔ Production میں demo users/passwords seed نہ کریں؛ اپنے platform administrator کو محفوظ طریقے سے provision کریں۔

## 4. Files deploy کریں

GitHub Actions workflow:

- Pull requests پر MySQL migrations، PHP syntax checks، automated tests اور role-based portal smoke tests چلاتا ہے۔
- FTP deployment صرف `main` branch پر push ہونے کے بعد چلتا ہے؛ repair branch یا pull request خود production deploy نہیں کرتی۔
- FTP deployment SQL migrations خود execute نہیں کرتا۔ یہ صرف versioned migration files کو server پر رکھتا ہے؛ database changes اوپر والے مرحلے کے مطابق CLI یا phpMyAdmin سے الگ apply کرنا ضروری ہے۔
- Legacy `database.sql`, `database/schema.sql` اور demo `database/seed.sql` deployment سے exclude ہیں؛ انہیں production پر upload/import نہ کریں۔

GitHub repository میں یہ Actions secrets configure کریں:

| Secret | Value |
|---|---|
| `FTP_SERVER` | Hosting کا FTP hostname |
| `FTP_USERNAME` | FTP username |
| `FTP_PASSWORD` | FTP password |

Deployment سے پہلے confirm کریں کہ FTP server directory درست ہے، مثلاً `htdocs/` یا hosting کی طرف سے دیا گیا document-root path۔ `dangerous-clean-slate` disabled ہے تاکہ server پر موجود غیر متعلقہ files delete نہ ہوں۔

## 5. Post-deployment verification

- `https://your-domain.example/auth/login.php` کھولیں۔
- Super Admin، School Admin، Teacher، Student اور Parent accounts سے login اور allowed pages چیک کریں۔
- ایک school کے account سے دوسرے school کے student/course/task/event IDs کھول کر دیکھیں؛ access block ہونا چاہیے۔
- Student creation، course enrollment، task status، attendance، grade entry، fee invoice/payment، leave approval اور notifications کے actual create/update flows test کریں۔
- Password reset request کریں اور SMTP inbox میں absolute `/auth/reset_password.php` link verify کریں۔
- Production میں demo credentials، `APP_DEBUG=true` یا temporary debug scripts استعمال نہ کریں۔

## 6. Current repository checks

CI میں automated test suite اور browser-style authenticated portal smoke checks چلتے ہیں۔ یہ checks hosting کے حقیقی SMTP delivery، FTP credentials، production database permissions یا production browser session کی جگہ نہیں لیتے؛ ان چیزوں کی الگ live verification ضروری ہے۔
