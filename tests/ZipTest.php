<?php
/**
 * OriginPHP Framework
 * Copyright 2018 - 2020 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @link        https://www.originphp.com
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */

namespace Origin\Test\Zip;

use Origin\Zip\Zip;
use Origin\Zip\FileObject;
use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Origin\Zip\Exception\ZipException;
use Origin\Zip\Exception\FileNotFoundException;

class ZipTest extends TestCase
{
    private static $archive;
    private static $supportsEncryption;

    public static function setUpBeforeClass(): void
    {
        static::$archive = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        static::$supportsEncryption = version_compare(phpversion(), '7.3', '>=');
    }
    
    public function testCreate()
    {
        $root = dirname(__DIR__);
        $archive = new Zip();
        $result = $archive->create(static::$archive)
            ->add($root . '/README.md')  // file
            ->add($root . '/LICENSE.md', ['compress' => false])  // file
            ->add($root . '/src') // directory
            ->save();
        $this->assertTrue($result);

        $this->expectException(ZipException::class);
        $unaccesibleFile = sys_get_temp_dir() . '/' . uniqid();
        mkdir($unaccesibleFile, 0000);
        $archive->create($unaccesibleFile);
    }

    public function testOpen()
    {
        $archive = new Zip();
        $this->assertInstanceOf(Zip::class, $archive->open(static::$archive));

        $this->expectException(FileNotFoundException::class);
        $archive->open('foo.zip');
    }

    public function testOpenError()
    {
        $unreadable = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        file_put_contents($unreadable, 'foo');
        chmod($unreadable, 000);
        $archive = new Zip();
        $this->expectException(ZipException::class);
        $this->assertInstanceOf(Zip::class, $archive->open($unreadable));
    }

    public function testNotOpenOrCreate()
    {
        $this->expectException(ZipException::class);
        (new Zip())->list();
    }

    /**
     *  [0] => Origin\Zip\FileObject Object
     *  (
     *      [name] => Exception/FileNotFoundException.php
     *      [size] => 501
     *      [timestamp] => 1576567932
     *      [compressedSize] => 298
     *      [encrypted] =>
     *  )
     *
     */

    public function testCount()
    {
        $archive = new Zip();
        $archive->open(static::$archive);
        $this->assertEquals(6, $archive->count());
    }

    public function testList()
    {
        $archive = new Zip();
        $archive->open(static::$archive);

        $list = $archive->list();
    
        $this->assertEquals(6, count($list));
        
        // on my mac Zip/FileObject are found using iterator in different order

        $file = $this->locate('README.md', $list);
        $this->assertInstanceOf(FileObject::class, $file);

        $file = $this->locate('LICENSE.md', $list);
        $this->assertInstanceOf(FileObject::class, $file);

        $file = $this->locate('FileObject.php', $list);
        $this->assertInstanceOf(FileObject::class, $file);

        $file = $this->locate('Zip.php', $list);
        $this->assertInstanceOf(FileObject::class, $file);

        $file = $this->locate('Exception/FileNotFoundException.php', $list);
        $this->assertInstanceOf(FileObject::class, $file);

        $file = $this->locate('Exception/ZipException.php', $list);
        $this->assertInstanceOf(FileObject::class, $file);
        
        // check keys
        $this->assertArrayHasKey('name', $file);
        $this->assertArrayHasKey('size', $file);
        $this->assertArrayHasKey('timestamp', $file);
        $this->assertArrayHasKey('compressedSize', $file);
     
        // check values
        $this->assertEquals('Exception/ZipException.php', $file['name']);
        $this->assertEquals(505, $file['size']);
        $this->assertGreaterThan(strtotime('-5 seconds'), $file['timestamp']);
        $this->assertEquals(294, $file['compressedSize']);

        if (static::$supportsEncryption) {
            $this->assertFalse($file['encrypted']);
            $this->assertArrayHasKey('encrypted', $file);
        }
    }

    private function locate(string $name, array $list) : ?FileObject
    {
        foreach ($list as $item) {
            if ($name === $item['name']) {
                return $item;
            }
        }

        return null;
    }

    public function testExists()
    {
        $archive = new Zip();
        $archive->open(static::$archive);
        // Check files
        $this->assertTrue($archive->exists('README.md'));
        $this->assertFalse($archive->exists('passwords.txt'));

        // Check directories
        $this->assertTrue($archive->exists('Exception/'));
        $this->assertFalse($archive->exists('Exception'));
    }

    /**
     * @depends testExists
     */
    public function testDelete()
    {
        $archive = new Zip();
        $archive->open(static::$archive);
        $this->assertTrue($archive->exists('README.md'));
        $archive->delete('README.md');

        $this->assertFalse($archive->exists('README.md'));

        $this->expectException(FileNotFoundException::class);
        $archive->delete('passwords.txt');
    }

    public function testAdd()
    {
        $archive = new Zip();
        $archive->open(static::$archive);
        $this->assertFalse($archive->exists('README.md'));
        $archive->add(dirname(__DIR__) .'/README.md');
        $this->assertTrue($archive->exists('README.md'));
    }

    public function testAddInvalidEncryption()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }
        $tmp = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        $this->expectException(InvalidArgumentException::class);
        (new Zip())->create('tmp.zip')->add('foo', ['encryption' => 'PGP']);
    }

    public function testPassword()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }

        $file = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        $archive = new Zip();
        $archive->create($file)->add(dirname(__DIR__) .'/README.md', ['password' => 'ladadiladada']);
        $this->assertTrue($archive->save());

        $archive = new Zip();
        $archive->open($file);
        $list = $archive->list();
        $this->assertTrue($archive->exists('README.md'));
        $this->assertEquals(1, $list[0]['encrypted']);
    }

    public function testExtract()
    {
        $destination = sys_get_temp_dir() . '/' . uniqid();

        $archive = new Zip();
        $archive->open(static::$archive);

        $this->assertTrue($archive->extract($destination));
        $this->assertFileExists($destination . '/README.md');
        $this->assertFileExists($destination . '/LICENSE.md');
        $this->assertFileExists($destination . '/Zip.php');
        $this->assertFileExists($destination . '/Exception/ZipException.php');
        $this->assertFileExists($destination . '/Exception/FileNotFoundException.php');
    }

    public function testExtractWithPassword()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }

        $destination = sys_get_temp_dir() . '/' . uniqid();
        $file = sys_get_temp_dir() . '/' . uniqid() . '.zip';

        $archive = new Zip();
        $archive->create($file)->add(dirname(__DIR__) .'/README.md', ['password' => 12345]);

        $this->assertTrue($archive->save());
        $archive->open($file);
        $this->assertFalse($archive->extract($destination));

        $this->assertTrue($archive->extract($destination, ['password' => 12345]));
        $this->assertSame(file_get_contents($destination . '/README.md'), file_get_contents(dirname(__DIR__) .'/README.md'));
    }

    public function testOverwrite()
    {
        $archive = new Zip();

        // sanity check
        $archive->open(static::$archive);
        $this->assertTrue($archive->exists('LICENSE.md'));

        // start test
        $archive->create(static::$archive, ['overwrite' => true])->add(dirname(__DIR__) .'/README.md')->save();
        $archive->open(static::$archive);

        $this->assertTrue($archive->exists('README.md'));
        $this->assertFalse($archive->exists('LICENSE.md'));
    }

    public function testEncrypt()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }

        $destination = sys_get_temp_dir() . '/' . uniqid();
        $file = sys_get_temp_dir() . '/' . uniqid() . '.zip';

        $archive = new Zip();
        $result = $archive->create($file)
            ->add(dirname(__DIR__) . '/README.md')  // file
            ->add(dirname(__DIR__) . '/src')  // file
            ->encrypt('foo')
            ->save();
        $this->assertTrue($result);
        
        $list = $archive->open($file)->list();
        
        $this->assertTrue($list[0]['encrypted']);
        $this->assertFalse($archive->extract($destination));
        $this->assertTrue($archive->extract($destination, ['password' => 'foo']));
    }

    public function testInvalidEncryption()
    {
        $this->expectException(InvalidArgumentException::class);
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }
        (new Zip())->create(sys_get_temp_dir() . '/' . uniqid() . '.zip')
            ->add(dirname(__DIR__) . '/README.md')  // file
            ->encrypt('foo', 'pgp')
            ->save();
    }

    public function testStore()
    {
        $file = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        $archive = new Zip();
        $result = $archive->create($file)
            ->add(dirname(__DIR__) . '/README.md', ['compress' => false])  // file
            ->save();
        $this->assertTrue($result);
        $list = $archive->open($file)->list();
        
        $this->assertEquals($list[0]['size'], $list[0]['compressedSize']);
    }

    public function testZip()
    {
        $result = Zip::zip(dirname(__DIR__) . '/src', static::$archive, ['overwrite' => true]);
        $this->assertTrue($result);

        $this->assertEquals(4, (new Zip)->open(static::$archive)->count());
    }

    public function testUnzip()
    {
        $destination = sys_get_temp_dir() . '/' . uniqid();
        $this->assertTrue(Zip::unzip(static::$archive, $destination));
    }

    public function testUnzipNotFound()
    {
        $this->expectException(FileNotFoundException::class);
        Zip::unzip(sys_get_temp_dir() . '/dota2.zip', sys_get_temp_dir());
    }

    public function testUnzipUnreadable()
    {
        $this->expectException(ZipException::class);

        $unreadable = sys_get_temp_dir() . '/' . uniqid() . '.zip';
        file_put_contents($unreadable, 'foo');
        chmod($unreadable, 000);
       
        Zip::unzip($unreadable, sys_get_temp_dir());
    }

    public function testZipWithPassword()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }

        $result = Zip::zip(dirname(__DIR__) . '/src', static::$archive, ['overwrite' => true,'password' => 1234]);
        $this->assertTrue($result);

        $this->assertEquals(4, (new Zip)->open(static::$archive)->count());
    }

    public function testUnzipWithPassword()
    {
        if (! static::$supportsEncryption) {
            $this->markTestSkipped('This requires PHP 7.3 and above');
        }

        $destination = sys_get_temp_dir() . '/' . uniqid();
        $this->assertFalse(Zip::unzip(static::$archive, $destination));
        $this->assertTrue(Zip::unzip(static::$archive, $destination, ['password' => 1234]));
    }

    public function testEncryptionNotSupported()
    {
        $this->expectException(BadMethodCallException::class);
        
        if (static::$supportsEncryption) {
            $this->markTestSkipped('This test is for PHP 7.2');
        }

        (new Zip())->create(sys_get_temp_dir() . '/' . uniqid() . '.zip')
            ->add(dirname(__DIR__) . '/README.md')  // file
            ->encrypt('foo');
    }

    public function testAddEncryptionNotSupported()
    {
        $this->expectException(BadMethodCallException::class);
        
        if (static::$supportsEncryption) {
            $this->markTestSkipped('This test is for PHP 7.2');
        }

        (new Zip())->create(sys_get_temp_dir() . '/' . uniqid() . '.zip')
            ->add(dirname(__DIR__) . '/README.md', ['password' => 'foo']);
    }

    public static function setUpAfterClass(): void
    {
        @unlink(static::$archive);
    }
}
