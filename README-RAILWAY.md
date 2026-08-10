# itVPN v2 — راهنمای Railway

این ربات **PHP + MySQL + Webhook** است (نه Python).

## ۱) GitHub
همه فایل‌های این زیپ را در یک ریپو آپلود کن.

## ۲) Railway
1. New Project → Deploy from GitHub
2. **حتماً یک سرویس MySQL اضافه کن** (Add → Database → MySQL)
3. MySQL را به سرویس ربات **Connect / Reference** کن تا متغیرهای MYSQL* تزریق شوند

## ۳) Variables روی سرویس ربات
حداقلی:
```
BOT_TOKEN=توکن_از_BotFather
ADMIN_ID=آیدی_عددی_تلگرام
CHANNEL=@channel یا آیدی
TZ=Asia/Tehran
```

اگر MySQL را Connect کرده باشی معمولاً این‌ها خودکار می‌آیند:
- MYSQLHOST
- MYSQLPORT
- MYSQLDATABASE
- MYSQLUSER
- MYSQLPASSWORD

اختیاری:
```
WEBHOOK_URL=https://دامنه-تو.up.railway.app/bot.php
WEB_URL=https://دامنه-تو.up.railway.app
SENDALL_MIN=300
```

اگر `RAILWAY_PUBLIC_DOMAIN` موجود باشد، setup.php خودش webhook را روی `/bot.php` ست می‌کند.

## ۴) بعد از Deploy
- لاگ باید بگوید: `Database ready` و ترجیحاً `Webhook set`
- Healthcheck روی `/` باید JSON با status ok برگرداند
- در تلگرام به ربات `/start` بزن

## ۵) نکات
- ربات باید در کانال اجباری (CHANNEL) ادمین باشد اگر عضویت اجباری داری
- توکن و پسورد را داخل گیت‌هاب نگذار
- این پروژه SQLite ندارد؛ بدون MySQL بالا نمی‌آید
