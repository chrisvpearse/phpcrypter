# A PHP Source Code Encrypter

The goal of this open source package is **security _through_ obscurity**.

It aims to offer an alternative to delivering your closed source projects in **plaintext**. Instead, you can opt to deliver them in **ciphertext** (encrypted), alongside a binary PHP extension which will decrypt them on the fly.

This package uses symmetric encryption, therefore the AES-256 key (which is only known to you as the developer), can be unique per project and/or release. To avoid being detected by hex editors (e.g. [Hex Fiend](https://hexfiend.com/)) and the [strings](https://www.unix.com/man-page/osx/1/strings) command, the key is stored within the binary as an XOR cipher, split into 32 parts. Additionally, the XOR key is also split into 32 parts. All 64 key parts are then shuffled together along with 64 _random_ key parts (128 parts in total) to ensure that the AES-256 and XOR key parts never appear in the same place twice.

#### Why encryption, not obfuscation?

If you search for an obfuscation package, there is almost always a complimentary deobfuscation package available (written by someone else), which renders the original package obsolete (unfortunately). On the other hand, AES-256 encryption hasn't been broken (yet)!

That being said, I would certainly consider obfuscation as a compliment to encryption. If your source code is obfuscated first (before encryption) and someone tries to reverse engineer your project by looking at the opcodes and stepping through it, it would be much more difficult to understand.

Typically, obfuscation focuses on altering the execution flow of your source code, combined with the scrambling of the names of your classes, methods, functions, variables and string literals. Because obfuscation essentially rewrites your code, it inevitably comes with a few "gotchas" along the way. Encryption, on the other hand, keeps your code intact (exactly as you wrote it).

## Requirements

### macOS/Linux

1. PHP ^8.2
2. `phpize`

### Windows

This package was built with support for Windows in mind, however, it has not been tested yet.

## Installation

The below assumes that you're currently in your application's root directory.

```console
$ composer require chrisvpearse/phpcrypter --dev
```

## Usage

### Generate a Key

```console
$ ./vendor/bin/phpcrypter generate [--clean] [--] <name> [<payload>]
```

The below command will generate a unique AES-256-CBC symmetric key named `foo`:

```console
$ ./vendor/bin/phpcrypter generate foo
```

Additionally, a `.phpcrypter/foo` directory will be created in your application's root, containing a PHP extension skeleton. The symmetric key is the :heart: of the skeleton :bone: — they will both be used to later build a binary PHP extension of the same name (`foo.so`).

A good rule of thumb is one key (and therefore one PHP extension) per project.

The output of the above command will be similar to the following:

```
Success!
Payload: pAYL0AD==
```

:exclamation: Please remember to add `/.phpcrypter` to your `.gitignore` file.

:bangbang: Additionally, it is important to save the payload in a password manager, such as [1Password](https://1password.com) or [pass](https://www.passwordstore.org).

### Build the PHP Extension

#### macOS/Linux

```console
$ cd .phpcrypter/foo
$ phpize
$ ./configure
$ make
$ make install
```

The above commands will build a PHP extension named `foo.so` and copy it into your PHP extension directory. The directory can be found via the following command:

```console
$ php -i | grep ^extension_dir
```

You should then add the following line to your `php.ini` configuration file:

```
extension=foo.so
```

The location of the loaded `php.ini` configuration file can be found via the following command:

```console
$ php -i | grep "Loaded Configuration File"
```

Next, verify that the extension is _loaded_:

```console
$ php -m | grep foo
foo
```

### Encrypt Directories and/or Files

```console
$ ./vendor/bin/phpcrypter encrypt <payload> <path>...
```

The below encrypts multiple directories and files at once. You must specify the previously obtained `payload` as the first argument.

```console
$ ./vendor/bin/phpcrypter encrypt "pAYL0AD==" \
  "dir-1" \
  "dir-2" \
  "file-1.php" \
  "file-2.php"
```

:exclamation: The contents of any PHP files found in the above paths will be overwritten. It is highly recommended that you create a new Git branch for these files:

```console
$ git checkout -b encrypted
```

#### Decrypt

If you're just experimenting, it's useful to be able to encrypt and decrypt at will. The below decrypts any directories and/or files previously encrypted with the `payload` argument:

```console
$ ./vendor/bin/phpcrypter decrypt <payload> <path>...
```

:exclamation: Again, the contents of any PHP files found in the above paths will be overwritten.

#### What does an encrypted file look like?

```php
<?php // @foo
if (! extension_loaded('foo')) exit('The "foo" extension is not loaded');
#pAYL0AD==

```

The PHP code block should be self explanatory, however, the final line contains a base64 encoded string containing the **phpcrypter** version, the IV (initialization vector) and the encrypted source code.

#### How does it work?

By default, when the extension is _loaded_, it simply hooks into the internals of PHP, namely the `zend_compile_file()` function, but it doesn't do anything, unless the `foo.decrypt` configuration option is set to `1` (it is set to `1` by default).

If you set `foo.decrypt` to `0` in your `php.ini` configuration file, it is recommended that you use `ini_set('foo.decrypt', 1)` in any unencrypted PHP files which `include`/`require` encrypted files. For example, if you would like to encrypt a controller, you should use `ini_set()` within an unencrypted base controller. You cannot use `ini_set()` within encrypted PHP files because `zend_compile_file()` works at a lower level.

Below are some [autocannon](https://github.com/mcollina/autocannon) benchmarks (10 connections for 10s):

| Extension Loaded | Extension Enabled | File Encrypted | Avg. Latency |
| ---------------- | ----------------- | -------------- | ------------ |
| No               | No                | No             | 2860.78 ms   |
| Yes              | `php.ini`         | No             | 2923.03 ms   |
| Yes              | `php.ini`         | Yes            | 2970.96 ms   |
| Yes              | `ini_set()`       | Yes            | 2890.86 ms   |

### Deployment

When you're ready to deploy your encrypted files, you should build an extension for that platform if it differs from your workstation, for example, Linux vs. macOS.

#### Global Installation

In the event that you need to install multiple extensions on the same server (for different projects), you should consider installing **phpcrypter** globally:

```console
$ composer global require chrisvpearse/phpcrypter
```

#### Generate a PHP Extension Skeleton With a Payload

You must specify the previously obtained `payload` as the second argument, so that the same key becomes the :heart: of this skeleton :bone: too.

```console
$ cd ~/.composer

$ export HISTCONTROL=ignorespace
$  ./vendor/bin/phpcrypter generate foo "pAYL0AD=="
$ unset HISTCONTROL
```

:bulb: Using `HISTCONTROL=ignorespace` prevents any commands that are prefixed with a space from appearing in your shell's history.

#### Build the PHP Extension Again

You should refer to the [previous section](#build-the-php-extension), following the appropriate steps for this particular platform.

#### Deploy

You are now ready to deploy your encrypted PHP files! :rocket:

## Credits

* [Christopher Pearse](https://x.com/chrisvpearse)

## License

The MIT License (MIT).
