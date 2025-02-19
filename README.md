# Fastly Image I/O Integration

[![Unofficial](https://img.shields.io/badge/Pantheon-Unofficial-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#unofficial)

## Overview

This WordPress plugin eliminates image derivatives stored in the filesystem, instead rendering them using the [Fastly IO service](https://www.fastly.com/documentation/reference/io/). This reduction will result in a drastically reduced overall filesystem size by a factor of 3-10 (depending on the overall amount of uploads).

## Requirements

* WordPress (recommended to be latest version)
* PHP 7.4+ (recommended - 8.1+)
* Site routing through Fastly Command
* Fastly IO service enabled


---

## **Features**

- **Admin Interface**
  - Provides an **admin page** in the WordPress **Dashboard** or **Network Admin** (for multisite setups).
  - Allows running media regeneration **via a button**.
  - Displays **output logs** in a textarea.
  - For **multisite setups**, provides:
    - A **multi-select dropdown** to choose specific subsites.
    - A checkbox to **run on all subsites**.

---

## Installation

This plugin may be installed by downloading the latest release, and placing in your plugins folder (`wp-content/plugins`).

Alternatively, you may install it via Composer.

## Post Installation Steps
After installation, you must regenerate all thumbnails. This can be done via the native WP-CLI command, `media regenerate`. 

```bash
wp media regenerate
```

Alternatively, you may log into your Dashboard, and users with administrator permissions may regenerate thumbnails via the "Fastly Media Regen" menu. For multisite installs, this is found in the Network Admin, and is limited to super-admins.


---

### **Admin Interface**

1. **For Single-Site WordPress**
   - Navigate to **Fastly Media Regen** in the WordPress Admin Menu.
   - Click the **"Regenerate Media"** button.

2. **For Multisite**
   - Go to **Network Admin → Fastly Media Regen**.
   - Select one or multiple subsites.
   - Click **"Regenerate Media"**.
   - View output in the textarea.

---

## **Troubleshooting**
  
- **Command fails in the admin panel but works in CLI**
  - Ensure `shell_exec()` is **enabled** on the server.
  - Check that **WP-CLI is available in the system path**.

---

## Running Development Tests 

1. Install MariaDB and SVN if you don't already have it

```bash
brew install mariadb svn
```

2. Make sure you're running PHP with mysql, gd, gd-jpeg, and gd-webp support. If you're using phpbrew, a command like below will install the right package of PHP.

```bash
phpbrew install 8.2.20 +default +fpm +pdo +mysql +gd +intl +openssl -- --with-jpeg --with-webp
```

3. Install PHP Unit

```bash
composer install
```

4. Initialize test environment

```bash
composer test-init
```

5. Run the tests

```bash
composer run test
```