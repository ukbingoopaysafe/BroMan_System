# قائمة مراجعة الأمان الشاملة لنظام BroMan

## ✅ قائمة المراجعة

استخدم هذه القائمة للتأكد من تطبيق جميع إجراءات الأمان قبل نشر التطبيق على الإنترنت.

---

## 🔹 1. قاعدة البيانات (MySQL)

### تغيير كلمة سر root

- [ ] فتح phpMyAdmin أو MySQL Command Line
- [ ] تنفيذ:
```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'كلمة_سر_قوية_جداً';
FLUSH PRIVILEGES;
```

### إنشاء مستخدم مخصص

- [ ] إنشاء مستخدم بصلاحيات محدودة:
```sql
CREATE USER 'broman_user'@'localhost' IDENTIFIED BY 'كلمة_سر_قوية';
GRANT SELECT, INSERT, UPDATE, DELETE ON broman_db.* TO 'broman_user'@'localhost';
FLUSH PRIVILEGES;
```

### تحديث إعدادات التطبيق

- [ ] تحديث `db.php` لاستخدام المستخدم الجديد (عبر environment variables)

### تشغيل Migration

- [ ] تشغيل ملف `migrations/add_login_attempts_table.sql`:
```sql
-- في phpMyAdmin أو MySQL CLI
SOURCE e:/xampp/htdocs/BroMan_System/migrations/add_login_attempts_table.sql;
```

---

## 🔹 2. إعدادات XAMPP Apache

### إخفاء معلومات السيرفر

- [ ] فتح `C:\xampp\apache\conf\httpd.conf`
- [ ] إضافة أو تعديل:
```apache
ServerTokens Prod
ServerSignature Off
```

### تعطيل الوصول للملفات الحساسة

- [ ] التأكد من وجود `.htaccess` المحدث ✅ (تم)

### تحديث php.ini

- [ ] فتح `C:\xampp\php\php.ini`
- [ ] تعديل الإعدادات التالية:
```ini
; إخفاء معلومات PHP
expose_php = Off

; تحسين أمان الـ session
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.cookie_samesite = "Lax"

; تعطيل عرض الأخطاء للمستخدمين
display_errors = Off
log_errors = On
error_log = "C:\xampp\php\logs\php_error.log"

; تحديد حجم الرفع
upload_max_filesize = 10M
post_max_size = 12M

; تعطيل دوال خطرة
disable_functions = exec,passthru,shell_exec,system,proc_open,popen
```

- [ ] إعادة تشغيل Apache بعد التعديلات

---

## 🔹 3. Windows Firewall

### تفعيل الـ Firewall

- [ ] فتح **Windows Security** → **Firewall & network protection**
- [ ] التأكد من تفعيل الـ Firewall لجميع الشبكات

### قواعد الـ Firewall

عند استخدام Cloudflare Tunnel، **لا تحتاج فتح أي بورت**. لكن تأكد من:

- [ ] حظر الوصول المباشر لـ Port 80 من الإنترنت
- [ ] حظر الوصول المباشر لـ Port 443 من الإنترنت
- [ ] حظر الوصول لـ Port 3306 (MySQL) من أي مكان غير localhost

```powershell
# حظر MySQL من الخارج (PowerShell كـ Administrator)
New-NetFirewallRule -DisplayName "Block MySQL External" -Direction Inbound -LocalPort 3306 -Protocol TCP -Action Block
```

---

## 🔹 4. Cloudflare Settings

### إعدادات SSL/TLS

- [ ] في Cloudflare Dashboard → SSL/TLS:
  - Mode: **Full** (أو Full Strict إذا عندك شهادة)
  - Always Use HTTPS: **On**
  - Automatic HTTPS Rewrites: **On**

### إعدادات الأمان

- [ ] في Security → Settings:
  - Security Level: **Medium** أو **High**
  - Challenge Passage: **30 minutes**
  - Browser Integrity Check: **On**

### Bot Protection

- [ ] في Security → Bots:
  - Bot Fight Mode: **On**

### Rate Limiting (اختياري - خطة مدفوعة)

- [ ] إعداد Rate Limiting Rules للحماية الإضافية

---

## 🔹 5. النسخ الاحتياطي

### إعداد نسخ احتياطي تلقائي

- [ ] إنشاء مجلد للنسخ الاحتياطية (خارج htdocs):
```powershell
mkdir C:\backups\broman
```

- [ ] إنشاء script للنسخ الاحتياطي `C:\backups\backup_broman.bat`:
```batch
@echo off
set DATE=%date:~10,4%%date:~4,2%%date:~7,2%
set BACKUP_DIR=C:\backups\broman

REM Backup database
"C:\xampp\mysql\bin\mysqldump" -u broman_user -pYOUR_PASSWORD broman_db > "%BACKUP_DIR%\db_%DATE%.sql"

REM Backup uploads
xcopy "E:\xampp\htdocs\BroMan_System\uploads" "%BACKUP_DIR%\uploads_%DATE%" /E /I /Y

REM Delete backups older than 30 days
forfiles -p "%BACKUP_DIR%" -s -m *.sql -d -30 -c "cmd /c del @path"
forfiles -p "%BACKUP_DIR%" -s -d -30 -c "cmd /c if @isdir==TRUE rd /s /q @path"

echo Backup completed: %DATE%
```

- [ ] إضافة Task Scheduler لتشغيل الـ script يومياً

---

## 🔹 6. المراقبة والتنبيهات

### مراقبة محاولات الدخول

- [ ] التحقق من جدول `login_attempts` دورياً:
```sql
-- عرض المحاولات الفاشلة في آخر 24 ساعة
SELECT ip_address, username, COUNT(*) as attempts, MAX(attempt_time) as last_attempt
FROM login_attempts 
WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address, username
ORDER BY attempts DESC;

-- عرض الـ IPs المحظورة حالياً
SELECT * FROM blocked_ips WHERE blocked_until > NOW();
```

### مراقبة ملفات الـ Log

- [ ] متابعة:
  - `C:\xampp\apache\logs\error.log`
  - `C:\xampp\php\logs\php_error.log`
  - `E:\xampp\htdocs\BroMan_System\session_activity.log`

---

## 🔹 7. تحديثات دورية

### تحديث البرمجيات

- [ ] تحديث XAMPP عندما تتوفر إصدارات جديدة
- [ ] تحديث PHP إلى أحدث إصدار مستقر
- [ ] تحديث Cloudflared بانتظام

### مراجعة أمنية دورية

- [ ] مراجعة هذه القائمة شهرياً
- [ ] اختبار النسخ الاحتياطي
- [ ] مراجعة صلاحيات المستخدمين

---

## 📊 ملخص الحالة

| البند | الحالة |
|-------|--------|
| Rate Limiting | ✅ تم تثبيته |
| CSRF Protection | ✅ تم تفعيله |
| Security Headers | ✅ تم إعدادها |
| .htaccess Protection | ✅ تم تحديثه |
| Database Migration | ⏳ يحتاج تشغيل |
| MySQL Password | ⏳ يحتاج تغيير |
| php.ini Settings | ⏳ يحتاج تعديل |
| Cloudflare Tunnel | ⏳ يحتاج إعداد |
| Backup System | ⏳ يحتاج إعداد |

---

## 🚨 تنبيهات مهمة

> ⚠️ **لا تنشر التطبيق قبل:**
> 1. تغيير كلمة سر MySQL
> 2. تشغيل migration جدول login_attempts
> 3. تعديل إعدادات php.ini
> 4. إعداد Cloudflare Tunnel

> ⚠️ **لا تشارك أبداً:**
> - كلمات سر قاعدة البيانات
> - ملفات `.env` أو `config.php`
> - Cloudflare Tunnel credentials
