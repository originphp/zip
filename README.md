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

### Compress

To compress a file or directory

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

### Uncompress/Unzip

To unzip a ZIP file

```php
Zip::unzip('/backups/today.zip','/a/folder');
```

## Fluent Interface

### Creating a new file

To create a new ZIP and files and directories. When you add a directory it will add all files and sub directories recursively.

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('README.md')
    ->add('src')
    ->save();
```

### Encryption

To encrypt all the files that you have added, call the `encrypt` method after adding the files, you can optionally supply the encryption method. Supported encryption methods are `aes128`,`aes192` and `aes256`.

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

Sometimes you might want certain files to not be compressed.

```php
$zip = new Zip();
$zip->create('/path/to/file.zip')
    ->add('README.md',['compress'=>false])
    ->save();
```

### Extracting/Unzipping

To extract a ZIP file

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