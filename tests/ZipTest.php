<?php
namespace Origin\Test\Zip;

use Origin\Zip\Zip;
use PHPUnit\Framework\TestCase;
use Origin\Zip\Exception\ZipException;
use Origin\Zip\Exception\FileNotFoundException;

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
    
        $this->assertEquals('README.md', $list[0]['name']);
        $this->assertEquals('Zip.php', $list[3]['name']);
        $this->assertEquals('Exception/FileNotFoundException.php', $list[5]['name']);
        $this->assertEquals('Exception/ZipException.php', $list[6]['name']);
      
        $this->assertEquals(2665, $list[4]['size']);
        $this->assertEquals(765, $list[4]['compressedSize']);
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
