# Fastly Image I/O Integration

[![Unofficial](https://img.shields.io/badge/Pantheon-Unofficial-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#unofficial)

## Installation

Install and activate the plugin following typical processes

After installation, regenerate the thumbnails for all images

```bash
wp media regenerate --yes
```

## Running tests

Install MariaDB and SVN if you don't already have it

```bash
brew install mariadb svn
```

Make sure you're running PHP with mysql, gd, gd-jpeg, and gd-webp support. If you're using phpbrew, a command like below will install the right package of PHP.

```bash
phpbrew install 8.2.20 +default +fpm +pdo +mysql +gd +intl +openssl -- --with-jpeg --with-webp
```

Install PHP Unit

```bash
composer install
```

Initialize test environment

```bash
composer test-init
```

Run the tests

```bash
composer run test
```

---

# **Fastly Media Regeneration for WordPress**

## **Overview**
This WordPress plugin provides a **WP-CLI command** and an **admin interface** for regenerating media thumbnails, with full support for **multisite environments**. It integrates Fastly caching strategies to ensure media updates propagate efficiently.

---

## **Features**
- **Custom WP-CLI Command (`wp fastlyio media regenerate`)**
  - Supports regenerating **all media** or **specific attachments**.
  - Allows passing a `--site` parameter to regenerate media on a **specific subsite** (via **Site ID** or **URL**).
  - Filters out non-image attachments.

- **Admin Interface**
  - Provides an **admin page** in the WordPress **Dashboard** or **Network Admin** (for multisite setups).
  - Allows running media regeneration **via a button**.
  - Displays **output logs** in a textarea.
  - For **multisite setups**, provides:
    - A **multi-select dropdown** to choose specific subsites.
    - A checkbox to **run on all subsites**.

---

## **Usage**
### **WP-CLI Command**
Run the following command to regenerate media:

```sh
wp fastlyio media regenerate
```

### **Optional Parameters**
| Parameter        | Description |
|-----------------|-------------|
| `--site=<site>` | Runs on a specific site. Accepts either **a Site ID** (for multisite) or **a full site URL**. |

#### **Examples**
- Regenerate all media:
  ```sh
  wp fastlyio media regenerate
  ```
- Regenerate media for a **specific subsite (by ID)**:
  ```sh
  wp fastlyio media regenerate --site=5
  ```
- Regenerate media for a **specific subsite (by URL)**:
  ```sh
  wp fastlyio media regenerate --site=https://example.com
  ```

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

## **Technical Details**
- **Admin Menu Location**
  - If **single-site**, appears in the **WordPress Admin Menu**.
  - If **multisite**, appears in **Network Admin**, restricted to **super-admins**.
  
- **Media Filtering**
  - **Only regenerates images** (skips PDFs and other attachments).
  - Uses `wp_get_attachment_metadata()` to check for `image_meta`.

- **Multisite Handling**
  - Allows regenerating media **for a specific site** (`--site=<id|url>`).
  - Supports regenerating media **for multiple subsites at once** via the **admin interface**.

---

## **Troubleshooting**
- **Unknown parameter: `--site`**
  - Ensure `WP_CLI\Utils\get_flag_value()` is correctly used in the CLI implementation.
  
- **Command fails in the admin panel but works in CLI**
  - Ensure `shell_exec()` is **enabled** on the server.
  - Check that **WP-CLI is available in the system path**.
