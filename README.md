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
