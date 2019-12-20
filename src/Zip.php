<?php
/**
 * OriginPHP Framework
 * Copyright 2018 - 2019 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @link        https://www.originphp.com
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */
declare(strict_types = 1);
namespace Origin\Zip;

use ZipArchive;
use BadMethodCallException;
use InvalidArgumentException;
use RecursiveIteratorIterator;

use RecursiveDirectoryIterator;
use Origin\Zip\Exception\ZipException;
use Origin\Zip\Exception\FileNotFoundException;

/**
 * A ZIP utility class
 */
class Zip
{
    /**
     * @var \ZipArchive
     */
    private $archive;

    /**
     * ZipArchive encryption functions require PHP 7.3, or you can use 7.2 but you need
     * to upgrade ZLIB to version 1.2 or higher.
     *
     * @var boolean
     */
    private $supportsEncryption = false;

    public function __construct()
    {
        $this->supportsEncryption = version_compare(phpversion(), '7.3', '>=');
    }

    /**
     * Gets the encryption method code
     *
     * Map of Encryption methods, not this requires zlib >= 1.2 which is not part
     * of the PHP 7.2 php zlib extension
     *
     * @param string $method
     * @return integer|null
     */
    private function encryptionMethod(string $method) : ?int
    {
        $encryptionMap = [
            'none' => ZipArchive::EM_NONE,
            'aes128' => ZipArchive::EM_AES_128,
            'aes192' => ZipArchive::EM_AES_192,
            'aes256' => ZipArchive::EM_AES_256
        ];

        return isset($encryptionMap[$method]) ? $encryptionMap[$method] : null;
    }

    /**
     * Creates a new ZIP file
     *
     * @param string $filename
     * @param array $options The following keys are supported
     *   - overwrite: default:false
     * @return \Oirigin\Zip\Zip
     */
    public function create(string $filename, array $options = []) : Zip
    {
        $options += ['overwrite' => false,'password' => null];
        $this->archive = new ZipArchive();
      
        if ($this->archive->open($filename, $options['overwrite'] ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::CREATE) !== true) {
            throw new ZipException(sprintf('Error opening %s', $filename));
        }
    
        return $this;
    }

    /**
     * Opens an existing ZIP archive
     *
     * @param string $filename
     * @return \Oirigin\Zip\Zip
     */
    public function open(string $filename) : Zip
    {
        if (! file_exists($filename)) {
            throw new FileNotFoundException(sprintf('%s could not be found', $filename));
        }

        $this->archive = new ZipArchive();
        if ($this->archive->open($filename) !== true) {
            throw new ZipException(sprintf('Error opening %s', $filename));
        }

        return $this;
    }

    /**
     * Adds a file or directory to the ZIP archive
     *
     * @param string $filename
     * @param array $options The following option keys are supported
     *   - compress: default:true set to false to just store
     *   - password: the password to set for this file
     *   - encryption: the encryption method to be used when setting a password
     * @return \Origin\Zip\Zip
     */
    public function add(string $item, array $options = []) : Zip
    {
        $options += ['encryption' => 'aes256','password' => null,'compress' => true];
       
        $this->checkArchive();

        if ($this->supportsEncryption and $this->encryptionMethod($options['encryption']) === null) {
            throw new InvalidArgumentException(sprintf('Unkown encryption type %s', $options['encryption']));
        }

        // php 7.2 zlib 1.1 issue
        if (! $this->supportsEncryption and $options['password']) {
            throw new BadMethodCallException('PHP 7.3 or greater required for encrypting files');
        }

        $item = str_replace('\\', '/', $item);

        if (is_file($item)) {
            $this->addFileToArchive(basename($item), $item, $options);

            return $this;
        }
        
        # Handle directories
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($item),
            RecursiveIteratorIterator::SELF_FIRST
        ); #! important
        
        foreach ($iterator as $iitem) {
            $path = $iitem->getPathName();
       
            $name = str_replace('\\', '/', $path);
     
            if (substr($name, -3) === '/..' or substr($name, -2) === '/.') {
                continue;
            }

            // remove item dir from name
            $name = str_replace($item . '/', '', $name);
            
            if (is_file($path)) {
                $this->addFileToArchive($name, $path, $options);
            } elseif (is_dir($path)) {
                $this->archive->addEmptyDir($name);
            }
        }

        return $this;
    }

    /**
     * Deletes a file from the ZIP archive
     *
     * @param string $name
     * @return \Origin\Zip\Zip
     */
    public function delete(string $name) : Zip
    {
        $this->checkArchive();

        if (! $this->archive->deleteName($name)) {
            throw new FileNotFoundException(sprintf('%s could not be found', $name));
        }

        return $this;
    }

    /**
     * Encrypts all the unencrypted files in this ZIP archive.
     *
     * @param string $password The password to use to encrypt
     * @param string $method default:aes256. Supported encryption aes128,aes192,aes256
     * @return \Origin\Zip\Zip
     */
    public function encrypt(string $password, string $method = 'aes256') : Zip
    {
        $this->checkArchive();

        if (! $this->supportsEncryption) {
            throw new BadMethodCallException('PHP 7.3 or greater required for encrypting files');
        }

        if ($this->encryptionMethod($method) === null) {
            throw new InvalidArgumentException(sprintf('Unkown encryption type %s', $method));
        }

        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            $file = $this->archive->statIndex($i);
            if (! $file or substr($file['name'], -1) === '/') {
                continue;
            }

            if (isset($file['encryption_method']) and $file['encryption_method'] === 0) {
                $this->archive->setEncryptionName($file['name'], $this->encryptionMethod($method), $password);
            }
        }

        return $this;
    }

    /**
     * List of files in the ZIP archive
     *
     * @param string $path Folder path to get e.g. Src/Controller
     * @return array
     */
    public function list(string $path = null) : array
    {
        $this->checkArchive();
        $length = $path ? strlen($path) + 1 : 0;

        $list = [];
        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            $file = $this->archive->statIndex($i);
            if (! $file or substr($file['name'], -1) === '/') {
                continue;
            }

            if (! $path or ($path and substr($file['name'], 0, $length) === $path . '/')) {
                $data = [
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'timestamp' => $file['mtime'],
                    'compressedSize' => $file['comp_size'],
                ];
                if (isset($file['encryption_method'])) {
                    $data['encrypted'] = $file['encryption_method'] !== 0;
                }
                $list[] = new FileObject($data);
            }
        }
    
        return $list;
    }

    /**
     * Counts the number of files in the ZIP archive.
     *
     * @internal not using ZipArchive:count since this counts directories as
     * files
     *
     * @return int
     */
    public function count() : int
    {
        $this->checkArchive();
        $count = 0;
        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            $file = $this->archive->statIndex($i);
            if ($file and substr($file['name'], -1) !== '/') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Checks if a file exists in an archive
     *
     * @param string $name
     * @return boolean
     */
    public function exists(string $name) : bool
    {
        $this->checkArchive();

        return ($this->archive->locateName($name) !== false);
    }

    /**
     * Saves the ZIP archive
     *
     * @param string $desination
     * @return boolean
     */
    public function save() : bool
    {
        $this->checkArchive();

        return $this->archive->close();
    }

    /**
     * Extracts this archive
     *
    * @param string $desination the directory to extract too
    * @param array $options the following options keys are supported
    *   - password: the password to use to decrypt the files
    *   - files: an array of files to extract
    * @return bool
    * @throws \Origin\Zip\Exception\FileNotFoundException
    */
    public function extract(string $desination, array $options = []) : bool
    {
        $this->checkArchive();

        $options += ['password' => null, 'files' => null];

        if ($options['password']) {
            $this->archive->setPassword((string) $options['password']);
        }

        return $this->archive->extractTo($desination, $options['files']);
    }

    /**
    * Adds the file to archive, and encrypts if needed
    *
    * @param string $name
    * @param string $filename
    * @return void
    */
    private function addFileToArchive(string $name, string $filename, array $options) : void
    {
        $this->archive->addFromString($name, file_get_contents($filename));
        if ($options['password'] !== null) {
            $this->archive->setEncryptionName($name, $this->encryptionMethod($options['encryption']), (string) $options['password']);
        }
        if ($options['compress'] === false) {
            $this->archive->setCompressionName($name, ZipArchive::CM_STORE);
        }
    }

    /**
    * Checks that an ZIP archive has been created or opened
    *
    * @return void
    */
    private function checkArchive() : void
    {
        if (! $this->archive) {
            throw new ZipException('No ZIP archive. Create a new or open an existing ZIP archive');
        }
    }

    # # # STATIC METHODS # # #

    /**
     * Creates a ZIP archive from the files or directories supplied
     *
     * @param string|array $source the name(s) of the file or directory to compress
     * @param string $desination file with full path to where the zip file will be stored
     * @param array $options The following option keys are supported
     *   - overwrite: overwrites an archive
     *   - password: password for this archive
     *   - encryption: encryption method to be used when password protecting this archive. aes128,aes192,aes256
     *   - compress: default:true. Set to false to just store.
     * @return bool
     * @throws \Origin\Zip\Exception\FileNotFoundException
     */
    public static function zip($source, string $desination, array $options = []) : bool
    {
        $options += ['encryption' => 'aes256','compress' => true, 'password' => null,'overwrite' => false];

        $archive = new Zip();
        $archive->create($desination, ['overwrite' => $options['overwrite']]);
     
        foreach ((array) $source as $item) {
            $archive->add($item, $options);
        }

        return $archive->save($desination);
    }

    /**
     * Unzips the ZIP file
     *
     * @param string $source the ZIP file to extract
     * @param string $desination the directory to extract too
     * @param array $options the following options keys are supported
     *   - password: the password to use to decrypt the files
     *   - files: an array of files to extract
     * @return bool
     * @throws \Origin\Zip\Exception\FileNotFoundException
     */
    public static function unzip(string $source, string $desination, array $options = []) : bool
    {
        $options += ['password' => null, 'files' => null];

        if (! file_exists($source)) {
            throw new FileNotFoundException(sprintf('%s could not be found', $source));
        }

        $archive = new ZipArchive();
        if ($archive->open($source) !== true) {
            throw new ZipException(sprintf('Error opening %s', $source));
        }

        if ($options['password']) {
            $archive->setPassword((string) $options['password']);
        }

        return $archive->extractTo($desination, $options['files']);
    }
}
