# Kişisel Ağ Günlüğü (Personal Weblog ~ 2000s)

2000'li yılların başındaki kişisel web sitelerinin samimi, yalın, dikkat dağıtmayan ve anında açılan ruhunu; modern, üst düzey güvenli ve temiz kod mimarisiyle harmanlayan kişisel blog sistemi.

---

## 🌟 Öne Çıkan Özellikler

* **Modern Temiz URL (Clean Slugs):**
  * `http://localhost:8000/yazi/merhaba-dunya-kisisel-web-gunlugum-yayinda`
  * `http://localhost:8000/arsiv`
  * `http://localhost:8000/defter`
  * `http://localhost:8000/hakkimda`
  * `http://localhost:8000/rss.xml`
  * Hem PHP dahili sunucusu (`router.php`) hem de Apache sunucuları (`.htaccess`) ile tam uyumlu.
* **Üst Düzey Güvenlik (Hardened Security):**
  * **HTTP Güvenlik Başlıkları:** `X-Frame-Options` (Clickjacking koruması), `X-Content-Type-Options: nosniff`, `Referrer-Policy`, modern sıkı `Content-Security-Policy` (CSP) ve `Permissions-Policy`.
  * **Çift Katmanlı Hız Sınırlayıcı (Rate Limiting):** Brute-force ve spam saldırılarına karşı hem `$_SESSION` hem de veritabanı `rate_limits` tablosu üzerinde **IP Hash** denetimi (saldırgan çerezleri silse dahi IP üzerinden engellenir).
  * **Anti-Bot Honeypot & Matematik Doğrulama:** Yorum ve defter formlarında botları yakalayan gizli tuzak alanlar ve tek kullanımlık dinamik matematik testi.
  * **CSRF & XSS Koruması:** Tüm formlarda ve silme/onaylama işlemlerinde kriptografik `csrf_token()` koruması, `ENT_HTML5` XSS filtreleme ve `safe_url()` ile `javascript:`, `data:` enjeksiyonlarının önlenmesi.
  * **Dizin Atlatma & Hassas Dosya Koruması (Path Traversal):** `router.php` ve `.htaccess` ile `config/`, `includes/`, `installed.lock` ve `..` içeren dizin atlatma isteklerine anında `403 Forbidden`.
  * **Session Hardening & Zaman Aşımı:** `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'`, tarayıcı imzası (User-Agent) eşleme, 2 saatlik hareketsizlik zaman aşımı ve giriş anında `session_regenerate_id(true)`.
  * **Zamanlama Saldırısı Koruması (Constant-time Auth):** Giriş panelinde kullanıcı mevcut olsun ya da olmasın sabit süreli `password_verify()` çalıştırılarak kullanıcı adı tespiti engellenir.
  * **SQL Injection Önleme:** Tüm sorgularda katı PDO Prepared Statements ve bağlantı hatalarında sunucu şifrelerini gizleyen güvenli hata yönetimi.
* **Kurulum Sihirbazı (`/install.php`):**
  * Veritabanı bağlantı bilgilerini, site başlığını ve yönetici hesabını tarayıcı üzerinden kolayca yapılandıran kurulum ekranı.
  * Kurulum tamamlandığında `config/installed.lock` ile güvenli şekilde kilitlenir.
* **Dinamik Kategori Sistemi & Modern Kategori URL'leri:**
  * Yönetim panelinde **[★ Kategoriler]** masası (`/admin/categories.php`) ile kategoriler oluşturulabilir, düzenlenebilir veya silinebilir.
  * Yazı editöründe kategori elle yazmak yerine dinamik `<select>` açılır kutusundan tek tıkla seçilir.
  * Kategori sayfaları temiz URL ile çalışır: `http://localhost:8000/kategori/yazilim`, `/kategori/nostalji` vb.
* **Özel "Hakkımda" Düzenleyicisi:**
  * Yönetim panelinde yer alan **[Hakkımda Düzenle]** menüsü ile biyografi metnini, sevilen şeyleri/hobileri ve dost siteler (blogroll) listesini tek sayfadan pratik biçimde yönetme.
* **Çevrimiçi CV / Özgeçmiş Sistemi (`/cv`):**
  * Yönetim panelindeki **[★ CV Düzenle]** masası üzerinden iş deneyimleri, eğitim, uzmanlıklar/beceriler ve projeler dinamik olarak yönetilebilir.
  * **Açma/Kapama Özelliği:** Kullanıcı CV sayfasını tek tıkla yayına alabilir veya yayından kaldırabilir. Yayından kaldırıldığında menüden gizlenir ve yalnızca yöneticiye özel taslak moduna geçer.
  * **Yazdır / PDF Desteği:** `@media print` stil desteğiyle gereksiz tüm web öğeleri gizlenerek doğrudan tek tıkla A4 formatında CV çıktısı veya PDF alınabilir.
* **2000'ler Ruhu (Gereksiz Teknik İfadelerden Arındırılmış):**
  * **Orijinal Hit Counter:** Sayfa altında nostaljik yeşil mekanik/dijital sayaç (`[ 0 0 1 0 2 6 ]`).
  * **Canlı Durum:** Webmaster'ın o anki ruh hali ve Winamp'tan dinlediği parça.
  * **Ziyaretçi Defteri:** Şehir ve hatıra notu bırakılabilen nostaljik defter.
  * **Dost Bağlantılar (Blogroll):** Eski internetin dost siteler listesi.
  * **Sıfır Efekt & 0ms Animasyon:** Anında yüklenen sayfalar, yüksek okunabilirlik.

---

## 🚀 Çalıştırma

Modern URL yönlendiricisi (`router.php`) ile PHP yerleşik geliştirme sunucusunu başlatın:

```powershell
php -S 127.0.0.1:8000 router.php
```

Tarayıcınızda açın:
* **Ana Sayfa:** [http://127.0.0.1:8000/](http://127.0.0.1:8000/)
* **Yazı Okuma:** [http://127.0.0.1:8000/yazi/saf-php-neden-hala-cok-guclu-ve-guzel](http://127.0.0.1:8000/yazi/saf-php-neden-hala-cok-guclu-ve-guzel)
* **Arşiv:** [http://127.0.0.1:8000/arsiv](http://127.0.0.1:8000/arsiv)
* **Ziyaretçi Defteri:** [http://127.0.0.1:8000/defter](http://127.0.0.1:8000/defter)
* **Hakkımda:** [http://127.0.0.1:8000/hakkimda](http://127.0.0.1:8000/hakkimda)
* **Çevrimiçi CV:** [http://127.0.0.1:8000/cv](http://127.0.0.1:8000/cv)
* **RSS Beslemesi:** [http://127.0.0.1:8000/rss.xml](http://127.0.0.1:8000/rss.xml)
* **Kurulum Sihirbazı:** [http://127.0.0.1:8000/install.php](http://127.0.0.1:8000/install.php)
* **Yönetici Paneli:** [http://127.0.0.1:8000/admin/](http://127.0.0.1:8000/admin/)

---

## 🔐 Yönetici Girişi

* **Kullanıcı Adı & Şifre:** Kurulum sihirbazı (`/install.php`) esnasında belirlediğiniz yönetici kimlik bilgileri geçerlidir.
* *(Giriş yaptıktan sonra **Site Ayarları** sekmesinden şifrenizi istediğiniz zaman değiştirebilirsiniz).*

---

## 📦 GitHub'dan Kurulum (Hızlı Başlangıç)

1. Depoyu klonlayın:
   ```bash
   git clone https://github.com/fatihsinankara/nobody.git
   cd nobody
   ```

2. PHP yerleşik sunucusunu çalıştırın:
   ```bash
   php -S 127.0.0.1:8000 router.php
   ```

3. Tarayıcınızda [http://127.0.0.1:8000/](http://127.0.0.1:8000/) adresine gidin. Sistem otomatik olarak sizi **Kurulum Sihirbazı**'na (`/install.php`) yönlendirecektir.
4. MySQL veritabanı bilgilerinizi ve yönetici şifrenizi girerek kurulumu tamamlayın.

> [!NOTE]
> Yerel veritabanı şifreleriniz ve kurulum kilidi (`config/database.php` & `config/installed.lock`) güvenlik amacıyla `.gitignore` ile yoksayılır. Projeyi çeken her kullanıcı kendi bağımsız kurulumunu sihirbaz üzerinden kolayca yapabilir.

---

## ⚡ cPanel Git Version Control ile Dağıtım (Deploy)

Proje kök dizininde hazır bir [`.cpanel.yml`](.cpanel.yml) dosyası bulunmaktadır. cPanel üzerinden tek tıkla canlıya almak için:

1. cPanel hesabınıza girip **Git™ Sürüm Kontrolü (Git™ Version Control)** menüsünü açın.
2. **Oluştur (Create)** butonuna basın ve GitHub depo adresinizi girin (`https://github.com/fatihsinankara/nobody.git`).
3. [`.cpanel.yml`](.cpanel.yml) dosyasındaki `DEPLOYPATH` satırında yer alan `username` kısmını kendi cPanel kullanıcı adınızla güncelleyin:
   ```yaml
   - export DEPLOYPATH=/home/KULLANICI_ADINIZ/public_html/
   ```
4. **Yönet (Manage) » Çek veya Dağıt (Pull or Deploy)** sekmesine geçip:
   * **Uzak Konumdan Güncelle (Update from Remote)**
   * **HEAD İşlemesini Dağıt (Deploy HEAD Commit)** butonuna tıklayın.
5. Proje dosyaları ve `.htaccess` otomatik olarak `public_html/` dizinine aktarılacak ve siteniz yayına hazır olacaktır!

---

## 📁 Dizin Mimarisi

```text
├── admin/
│   ├── index.php              # Kontrol masası, istatistikler ve yazı yönetimi
│   ├── post_edit.php          # Yazı ekleme & düzenleme
│   ├── categories.php         # Dinamik kategori CRUD yönetimi
│   ├── about_edit.php         # Hakkımda & Blogroll düzenleyicisi
│   ├── cv_edit.php            # Çevrimiçi CV / Özgeçmiş yönetimi
│   ├── comments.php           # Yorum yönetimi (onay/silme)
│   ├── guestbook.php          # Ziyaretçi defteri yönetimi
│   ├── settings.php           # Başlık, slogan, modül açma/kapama ve şifre değiştirme
│   ├── login.php              # Rate-limit korumalı yönetici girişi
│   └── nav.php                # Yönetim gezinme çubuğu
├── assets/
│   ├── style.css              # Saf 2000'ler retro-modern ve responsive CSS stili
│   └── app.js                 # Minimal saf JS etkileşimleri (onay pencereleri ve yazdırma)
├── config/
│   ├── database.example.php   # Örnek veritabanı şablonu (GitHub için)
│   ├── database.php           # Yerel MySQL PDO bağlantısı (git-ignored)
│   ├── init.php               # Oturum, tekil sayaç ve genel başlatıcı
│   └── installed.lock         # Kurulum kilit dosyası (git-ignored)
├── includes/
│   ├── schema.php             # Tekil veritabanı şeması ve sürüm geçişleri (migrations)
│   ├── functions.php          # CSRF, rate limit, slug, sayaç ve güvenlik fonksiyonları
│   ├── header.php             # Nostaljik başlık ve dinamik menü şablonu
│   ├── sidebar.php            # Arama, kategoriler, son yazılar, blogroll
│   └── footer.php             # Tekil sayaç, çalışma süresi ve nostaljik rozetler
├── index.php                  # Ana sayfa ve kronolojik arşiv
├── post.php                   # Tekil yazı detay & yorumlar
├── guestbook.php              # Ziyaretçi defteri
├── about.php                  # Hakkımda & Blogroll sayfası
├── cv.php                     # Çevrimiçi CV ve yazdırma / PDF sayfası
├── rss.php                    # Standart RSS 2.0 XML akışı
├── install.php                # Kurulum sihirbazı
├── router.php                 # Yerel geliştirme temiz URL yönlendiricisi
├── .htaccess                  # Apache mod_rewrite ve dizin koruma kuralları
├── .cpanel.yml                # cPanel Git Version Control otomatik dağıtım yapılandırması
├── .gitignore                 # Güvenlik ve temiz depo yapılandırması
└── README.md
```
