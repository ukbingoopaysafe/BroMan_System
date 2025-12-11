# دليل إعداد Cloudflare Tunnel لنظام BroMan

## 📋 المتطلبات الأساسية

- حساب على [Cloudflare](https://dash.cloudflare.com/sign-up) (مجاني)
- دومين مُضاف إلى Cloudflare (سنشرح كيف تنقل الدومين من Hostinger)
- جهاز السيرفر يعمل بنظام Windows مع XAMPP

---

## 🔹 الخطوة 1: نقل الدومين إلى Cloudflare

### 1.1 إضافة الدومين إلى Cloudflare

1. سجل دخول إلى [Cloudflare Dashboard](https://dash.cloudflare.com)
2. اضغط **Add a Site**
3. أدخل اسم الدومين (مثل: `yourdomain.com`)
4. اختر الخطة **Free**
5. Cloudflare سيعمل scan للـ DNS records الموجودة

### 1.2 تغيير Nameservers في Hostinger

1. سجل دخول إلى Hostinger
2. اذهب إلى **Domains** → اختر الدومين
3. اضغط **DNS / Nameservers**
4. غيّر الـ Nameservers إلى اللي Cloudflare أعطاهم لك:
   ```
   مثال:
   chad.ns.cloudflare.com
   vera.ns.cloudflare.com
   ```
5. انتظر من 24-48 ساعة لتفعيل التغيير (عادةً بيكون أقل)

---

## 🔹 الخطوة 2: تثبيت Cloudflared على Windows

### 2.1 تحميل Cloudflared

1. اذهب إلى [Cloudflare Tunnel Downloads](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
2. حمل **cloudflared-windows-amd64.msi** (لـ Windows 64-bit)
3. ثبّت البرنامج

### 2.2 التحقق من التثبيت

افتح PowerShell وشغل:
```powershell
cloudflared --version
```

يجب يظهر رقم الإصدار.

---

## 🔹 الخطوة 3: تسجيل الدخول وإنشاء Tunnel

### 3.1 تسجيل الدخول

```powershell
cloudflared tunnel login
```

- سيفتح المتصفح لتسجيل الدخول إلى Cloudflare
- اختر الدومين اللي تريد ربطه
- سيتم حفظ ملف الـ credentials تلقائياً

### 3.2 إنشاء Tunnel جديد

```powershell
cloudflared tunnel create broman-tunnel
```

سيظهر **Tunnel ID** - احفظه!

### 3.3 التحقق من إنشاء Tunnel

```powershell
cloudflared tunnel list
```

---

## 🔹 الخطوة 4: إعداد Tunnel Configuration

### 4.1 إنشاء ملف الإعدادات

أنشئ ملف `config.yml` في المسار:
```
C:\Users\<YourUsername>\.cloudflared\config.yml
```

### 4.2 محتوى ملف config.yml

```yaml
tunnel: <TUNNEL_ID>
credentials-file: C:\Users\<YourUsername>\.cloudflared\<TUNNEL_ID>.json

ingress:
  # الدومين الرئيسي
  - hostname: broman.yourdomain.com
    service: http://localhost:80
  
  # يمكن إضافة subdomains أخرى
  # - hostname: api.yourdomain.com
  #   service: http://localhost:8080
  
  # Catch-all rule (مطلوب)
  - service: http_status:404
```

استبدل:
- `<TUNNEL_ID>` بالـ ID اللي حصلت عليه
- `<YourUsername>` باسم المستخدم على Windows
- `yourdomain.com` بالدومين الفعلي

---

## 🔹 الخطوة 5: إضافة DNS Record

```powershell
cloudflared tunnel route dns broman-tunnel broman.yourdomain.com
```

هذا سيضيف CNAME record تلقائياً في Cloudflare.

---

## 🔹 الخطوة 6: تشغيل Tunnel

### 6.1 تشغيل يدوي (للاختبار)

```powershell
cloudflared tunnel run broman-tunnel
```

### 6.2 تثبيت كـ Windows Service (للتشغيل التلقائي)

```powershell
cloudflared service install
```

ثم شغّل الـ service:
```powershell
net start cloudflared
```

---

## 🔹 الخطوة 7: التحقق من العمل

1. افتح المتصفح واذهب إلى `https://broman.yourdomain.com`
2. يجب يظهر التطبيق ويكون الاتصال آمن (HTTPS) ✅

---

## 🔐 إعدادات أمان إضافية في Cloudflare

### تفعيل Cloudflare Access (اختياري ولكن موصى به)

1. اذهب إلى **Zero Trust** في Cloudflare Dashboard
2. اضغط **Access** → **Applications**
3. اضغط **Add an application**
4. اختر **Self-hosted**
5. أدخل:
   - **Application name**: BroMan System
   - **Session Duration**: 24 hours (أو حسب الحاجة)
   - **Application domain**: `broman.yourdomain.com`
6. في **Policies**:
   - **Policy name**: Employees Only
   - **Action**: Allow
   - **Include**: Emails ending in `@yourcompany.com`
   
هذا سيضيف طبقة حماية إضافية تتطلب تسجيل دخول Cloudflare قبل الوصول للتطبيق.

---

## 🔧 أوامر مفيدة

| الأمر | الوصف |
|-------|-------|
| `cloudflared tunnel list` | عرض جميع الـ Tunnels |
| `cloudflared tunnel info <name>` | معلومات عن tunnel معين |
| `cloudflared tunnel run <name>` | تشغيل tunnel |
| `cloudflared service install` | تثبيت كـ service |
| `cloudflared service uninstall` | إزالة الـ service |
| `net start cloudflared` | بدء الـ service |
| `net stop cloudflared` | إيقاف الـ service |

---

## ❓ استكشاف الأخطاء

### الخطأ: "connection refused"
- تأكد أن XAMPP Apache يعمل
- تأكد أن `localhost:80` يستجيب

### الخطأ: "bad gateway"
- تحقق من ملف `config.yml`
- تأكد أن الـ service URL صحيح

### الخطأ: "tunnel not found"
- تحقق أن الـ Tunnel ID صحيح
- شغّل `cloudflared tunnel list` للتأكد

---

## 📌 ملاحظات مهمة

1. **HTTPS تلقائي**: Cloudflare يوفر SSL/TLS مجاناً - لا تحتاج شهادة على السيرفر المحلي
2. **IP مخفي**: Cloudflare يخفي IP السيرفر الحقيقي
3. **DDoS Protection**: حماية تلقائية من هجمات DDoS
4. **لا حاجة لفتح بورتات**: Tunnel يعمل من الداخل للخارج، لا تحتاج فتح أي بورت في الراوتر
