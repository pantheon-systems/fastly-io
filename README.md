# Fastly Image I/O Integration

[![Unofficial](https://img.shields.io/badge/Pantheon-Unofficial-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#unofficial)

## Overview

This WordPress plugin eliminates image derivatives stored in the filesystem, instead rendering them using the [Fastly IO service](https://www.fastly.com/documentation/reference/io/). This reduction will result in a drastically reduced overall filesystem size by a factor of 3-10 (depending on the overall amount of uploads).

## Requirements

* WordPress (recommended to be latest version)
* PHP 7.4+ (recommended - 8.1+)
* Site routing through Fastly (Pantheon's AGCDN Image I/O)
* Fastly IO service enabled


---

## **Features**


## Installation

This plugin may be installed by downloading the latest release, and placing in your plugins folder (`wp-content/plugins`).

Alternatively, you may install it via Composer.

## Post Installation Steps
After installation, you must regenerate all thumbnails. This can be done via the native WP-CLI command, `media regenerate`. 

```bash
wp media regenerate
```

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