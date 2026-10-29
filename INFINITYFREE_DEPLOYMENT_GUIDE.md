# 🚀 Academics — InfinityFree Deployment & GitHub CI/CD Guide

یہ گائیڈ آپ کو بتائے گی کہ کس طرح آپ اپنے پروجیکٹ کو **InfinityFree** پر آسانی سے اپلوڈ کر سکتے ہیں اور **GitHub Actions** کے ذریعے خودکار (Automatic) کنیکٹ کر سکتے ہیں تاکہ جب بھی آپ GitHub پر کوڈ پش (push) کریں، آپ کی ویب سائٹ خود بخود InfinityFree پر اپڈیٹ ہو جائے۔

---

## 📋 مرحلہ 1: ڈیٹا بیس اپلوڈ کرنا (Database Setup)

آپ کے پروجیکٹ کے فولڈر میں ہم نے ایک تیار شدہ ڈیٹا بیس فائل بنا دی ہے:
📁 **`edusphere_infinityfree_database.sql`**

1. سب سے پہلے اپنے **InfinityFree Client Area** یا **vPanel (Control Panel)** میں لاگ ان کریں۔
2. **"MySQL Databases"** والے آپشن پر کلک کریں۔
3. نیا ڈیٹا بیس بنائیں (مثلاً `edusphere` لکھ کر Create پر کلک کریں)۔ آپ کے ڈیٹا بیس کا پورا نام کچھ اس طرح بن جائے گا:
   `if0_38472918_edusphere`
4. اب واپس آ کر **"phpMyAdmin"** پر کلک کریں اور اپنے بنائے ہوئے ڈیٹا بیس کو سلیکٹ کریں۔
5. اوپر مینیو میں **"Import"** ٹیب پر کلک کریں۔
6. **"Choose File"** پر کلک کر کے اپنے کمپیوٹر سے **`edusphere_infinityfree_database.sql`** فائل منتخب کریں۔
7. نیچے **"Go"** بٹن پر کلک کریں۔ پورا ڈیٹا بیس تمام ٹیبلز اور ڈیمو ڈیٹا کے ساتھ اپلوڈ ہو جائے گا!

---

## ⚙️ مرحلہ 2: ڈیٹا بیس کی کنکشن سیٹنگز (Database Config)

پروجیکٹ کی فائل **`config/db_config.php`** کو اوپن کریں:

```php
// InfinityFree / Live Hosting پر چلانے کے لیے $FORCE_PRODUCTION کو true کریں:
$FORCE_PRODUCTION = true;

if ($isInfinityFree || $FORCE_PRODUCTION) {
    define('DB_HOST', 'sqlxxx.infinityfree.com'); // اپنا MySQL Hostname لکھیں
    define('DB_NAME', 'if0_xxxxxxxx_edusphere');  // اپنا Database Name لکھیں
    define('DB_USER', 'if0_xxxxxxxx');            // اپنا MySQL Username لکھیں
    define('DB_PASS', 'YOUR_INFINITYFREE_PASS');  // اپنا vPanel والا پاس ورڈ لکھیں
    define('DB_PORT', 3306);
    define('IS_PRODUCTION', true);
}
```

> **نوٹ:** جب آپ لوکل XAMPP پر چلا رہے ہوں تو `$FORCE_PRODUCTION = false;` ہی رہنے دیں، سسٹم خود بخود لوکل ڈیٹا بیس سے جڑ جائے گا۔

---

## 🔄 مرحلہ 3: GitHub کو InfinityFree سے خودکار کنیکٹ کرنا (GitHub Actions Auto-Deploy)

ہم نے آپ کے لیے **GitHub Actions Workflow** فائل **`.github/workflows/deploy.yml`** بنا دی ہے۔
اب جب بھی آپ `git push origin main` کریں گے، GitHub خود بخود آپ کی فائلز InfinityFree پر بھیج دے گا۔

اسے چالو کرنے کے لیے صرف یہ 3 چیزیں GitHub میں شامل کرنی ہیں:

1. اپنے **GitHub Repository** پر جائیں۔
2. **Settings** ➔ **Secrets and variables** ➔ **Actions** پر کلک کریں۔
3. **"New repository secret"** بٹن پر کلک کر کے یہ 3 سیکرٹس ایڈ کریں:

| Secret Name | کہاں سے ملے گا؟ (Value) | مثال |
|---|---|---|
| **`FTP_SERVER`** | InfinityFree اکاؤنٹ کی FTP Details میں "FTP Hostname" | `ftpupload.net` |
| **`FTP_USERNAME`** | InfinityFree کا "FTP User Name" | `if0_38472918` |
| **`FTP_PASSWORD`** | آپ کے InfinityFree اکاؤنٹ کا پاس ورڈ | `vPanel Password` |

### ✅ ٹیسٹ کرنا:
جیسے ہی آپ سیکرٹس ایڈ کر کے کوڈ گٹ ہب پر پش کریں گے:
- GitHub میں **"Actions"** ٹیب پر جائیں۔
- آپ دیکھیں گے کہ **"Deploy Academics to InfinityFree"** کا ایکشن چل پڑے گا اور 1 منٹ کے اندر آپ کی ویب سائٹ InfinityFree پر لائیو ہو جائے گی!

---

## 🔑 ڈیمو لاگ ان اکاؤنٹس (Demo Login Credentials)

ڈیٹا بیس اپلوڈ ہونے کے بعد آپ ان اکاؤنٹس سے لاگ ان کر سکتے ہیں:

- **Super Admin:**
  - Email: `admin@edusphere.com`
  - Password: `Admin@123`
- **Teacher:**
  - Email: `teacher@edusphere.com`
  - Password: `Teacher@123`
- **Student:**
  - Email: `student@edusphere.com`
  - Password: `Student@123`
