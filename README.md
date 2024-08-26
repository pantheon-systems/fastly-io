
[![Unofficial](https://img.shields.io/badge/Pantheon-Unofficial-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#unofficial)

## Running tests

Install MySQL and SVN if you don't already have it

```bash
brew install mysql svn
```

Make sure you're running PHP with mysql, gd, gd-jpeg, and gd-webp support. If you're using phpbrew, a command like below will install the right package of PHP.

```bash
phpbrew install 8.2.20 +default +fpm +pdo +mysql +gd +intl +openssl -- --with-jpeg --with-webp
```

Initialize test environment

```bash
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Install PHP Unit

```bash
composer install
```

Run the tests

```bash
composer run test
```
