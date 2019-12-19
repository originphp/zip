<?php
namespace Origin\Test\Zip;

use Origin\Zip\Zip;
use PHPUnit\Framework\TestCase;
use Origin\Zip\Exception\ZipException;
use Origin\Zip\Exception\FileNotFoundException;
use Origin\Zip\FileObject;

class ZipTest extends TestCase
{
    private static $archive;

    public static function setUpBeforeClass(): void
    {
        static::$archive = sys_get_temp_dir() . '/' . uniqid() . '.zip';
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

    public function testOverwrite()
    {
        $this->markTestIncomplete('Not implemented yet');
    }

    public function testOpen()
    {
        $archive = new Zip();
        $this->assertInstanceOf(Zip::class, $archive->open(static::$archive));

        $this->expectException(FileNotFoundException::class);
        $archive->open('foo.zip');
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
        $this->assertArrayHasKey('encrypted', $file);

        // check values
        $this->assertEquals('Exception/ZipException.php', $file['name']);
        $this->assertEquals(505, $file['size']);
        $this->assertGreaterThan(strtotime('-5 seconds'), $file['timestamp']);
        $this->assertEquals(294, $file['compressedSize']);
        $this->assertFalse($file['encrypted']);
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
    }

    public function testAdd()
    {
        $archive = new Zip();
        $archive->open(static::$archive);
        $this->assertFalse($archive->exists('README.md'));
        $archive->add(dirname(__DIR__) .'/README.md');
        $this->assertTrue($archive->exists('README.md'));
    }

    public function testPassword()
    {
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

    public static function setUpAfterClass(): void
    {
        @unlink(static::$archive);
    }
}
