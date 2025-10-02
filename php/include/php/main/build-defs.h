/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Author: Stig Sæther Bakken <ssb@php.net>                             |
   +----------------------------------------------------------------------+
*/

#define CONFIGURE_COMMAND " './configure'  '--prefix=/tmp/php-static' '--disable-all' '--enable-cli' '--enable-fpm' '--enable-static' '--disable-shared' '--with-config-file-path=/tmp/php-static/etc' '--with-config-file-scan-dir=/tmp/php-static/etc/conf.d' '--enable-json' '--enable-hash' '--enable-sqlite3' '--enable-zip' '--enable-curl' '--enable-openssl' '--enable-readline' '--enable-libxml' '--enable-phar' '--with-libxml' '--with-zlib' '--enable-zts=no' '--disable-debug' '--disable-rpath' '--disable-static' '--enable-shared=no' '--with-pic' '--disable-ipv6' '--without-pear' '--without-iconv' '--without-gettext' '--disable-cgi' '--disable-phpdbg' '--enable-embed=no' '--disable-zend-signals' '--enable-zend-max-execution-timers'"
#define PHP_ODBC_CFLAGS	""
#define PHP_ODBC_LFLAGS		""
#define PHP_ODBC_LIBS		""
#define PHP_ODBC_TYPE		""
#define PHP_OCI8_DIR			""
#define PHP_OCI8_ORACLE_VERSION		""
#define PHP_PROG_SENDMAIL	"/usr/sbin/sendmail"
#define PEAR_INSTALLDIR         ""
#define PHP_INCLUDE_PATH	".:"
#define PHP_EXTENSION_DIR       "/tmp/php-static/lib/php/extensions/no-debug-non-zts-20220829"
#define PHP_PREFIX              "/tmp/php-static"
#define PHP_BINDIR              "/tmp/php-static/bin"
#define PHP_SBINDIR             "/tmp/php-static/sbin"
#define PHP_MANDIR              "/tmp/php-static/php/man"
#define PHP_LIBDIR              "/tmp/php-static/lib/php"
#define PHP_DATADIR             "/tmp/php-static/share/php"
#define PHP_SYSCONFDIR          "/tmp/php-static/etc"
#define PHP_LOCALSTATEDIR       "/tmp/php-static/var"
#define PHP_CONFIG_FILE_PATH    "/tmp/php-static/etc"
#define PHP_CONFIG_FILE_SCAN_DIR    "/tmp/php-static/etc/conf.d"
#define PHP_SHLIB_SUFFIX        "so"
#define PHP_SHLIB_EXT_PREFIX    ""
