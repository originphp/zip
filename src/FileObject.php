<?php
/**
 * OriginPHP Framework
 * Copyright 2018 - 2021 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright     Copyright (c) Jamiel Sharief
 * @link         https://www.originphp.com
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
declare(strict_types = 1);
namespace Origin\Zip;

use ArrayAccess;

/**
 * FileObject for holding file result can work as array or object
 */
class FileObject implements ArrayAccess
{
    private $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * part of the ArrayAccess Inferface
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * part of the ArrayAccess Inferface
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * part of the ArrayAccess Inferface
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * part of the ArrayAccess Inferface
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Gets data from the container
     *
     * @param string $key
     * @return void
     */
    public function &__get($key)
    {
        return $this->data[$key];
    }

    /**
     * Magic method set data in container
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Magic method check if key exists in container
     *
     * @param string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Magic method unset data in container
     *
     * @param string $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Returns this object properties as an array
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->data;
    }
}
