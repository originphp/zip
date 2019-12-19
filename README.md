# Zip (alpha)

![license](https://img.shields.io/badge/license-MIT-brightGreen.svg)
[![build](https://travis-ci.org/originphp/zip.svg?branch=master)](https://travis-ci.org/originphp/zip)
[![coverage](https://coveralls.io/repos/github/originphp/zip/badge.svg?branch=master)](https://coveralls.io/github/originphp/zip?branch=master)

A ZIP utility for creating and unziping ZIP files.

## Installation

To install this package

```linux
$ composer require originphp/zip
```

## Static Methods

### Create a ZIP Archive

To create a ZIP archive using a file or directory

```php
Zip::zip(__DIR__ .'/src','/backups/today.zip');
```

You can also ZIP multiple files or directories

```php
Zip::zip([
    'README.md',
    __DIR__ .'/src'
    ],'/backups/today.zip');
```

You can also pass any of the following options keys

- password: a password used to encrypt the archive with
- compress: default:`true`. Set to true if you just want to store the files without compression
- encryption: default:`aes256`. This is the encryption method used when using a password. Supported encryption methods: `aes128`,`aes192`,and `aes256`. 

> To encrypt files with passwords you need to be using PHP 7.3 or above.

```php
Zip::zip(__DIR__ .'/src','/backups/today.zip',[
    'password'=>'passw0rd'
    ]);
```

### Unzip a ZIP Archive

To unzip a ZIP file

```php
Zip::unzip('/backups/today.zip','/a/folder');
```

## Fluent Interface

### Create a ZIP Archive

To create a new ZIP and files and directories. When you add a directory it will add all files and sub directories recursively.

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('README.md')
    ->add('src')
    ->save();
```

### Encryption

> To encrypt files with passwords you need to be using PHP 7.3 or above.

To encrypt all the files in the archive, call the `encrypt` method after adding the files, you can optionally supply the encryption method. Supported encryption methods are `aes128`,`aes192` and `aes256`.

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('README.md')
    ->add('src')
    ->encrypt('passw0rd')
    ->save();
```

If just want to encrypt certain files

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('README.md')
    ->add('Financials.xlsx',['password' => 'secret'])
    ->save();
```

### Compression

If you just want to store a file in the ZIP archive without compression you can set `compress` to `false`.

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('logo.jpg',['compress'=>false])
    ->save();
```

### Unzip a ZIP Archive

To extract files from a ZIP file

```php
$zip = new Zip();
$zip->open('/path/to/file.zip')
    ->extract('/destination/folder')
```

If the ZIP file has encrypted files

```php
$zip = new Zip();
$zip->open('/path/to/file.zip')
    ->extract('/destination/folder',['password'=>'foo']);
```

You can also extract selected files

```php
$zip = new Zip();
$zip->open('/path/to/file.zip')
    ->extract('/destination/folder',[
        'files'=>[
            'README.md',
            'LICENSE.md'
        ]
    ]);
```

### Listing contents

To list contents of a ZIP file

```php
$zip = new Zip();
$list = $zip->open('/path/to/file.zip')
            ->list();
```

This will output like this

```
Array
(
    [0] => Origin\Zip\FileObject Object
        (
            [name] => README.md
            [size] => 666
            [timestamp] => 1576656596
            [compressedSize] => 371
            [encrypted] => 1
        )
)
```

You can also just list files from within a folder of the ZIP file

```php
$zip = new Zip();
$testFiles = $zip->open('/path/to/file.zip')
                 ->list('tests');
```

### Deleting Files

To delete a file from a ZIP archive

```php
$zip = new Zip();
$zip->open('/path/to/file.zip')
    ->delete('tests/ControllerTest.php');
```