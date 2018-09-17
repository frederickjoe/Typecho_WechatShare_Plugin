<?php

class MemcachedClient {

    private static $_instance = null;
    /**
     * @var Memcached
     */
    private $mc = null;
    private $host = '127.0.0.1';
    private $port = 11211;
    private $expire = 7100;

    private function __construct($option = null) {
        if (isset($option['host'])) {
            $this->host = $option['host'];
        }
        if (isset($option['port'])) {
            $this->port = $option['port'];
        }
        if (isset($option['expire'])) {
            $this->expire = $option['expire'];
        }
        $this->init();
    }

    static public function getInstance($option) {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self($option);
        }

        return self::$_instance;
    }

    public function init() {
        try {
            $this->mc = new Memcached();
            $this->mc->addServer($this->host, $this->port);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function add($key, $value, $expire = null) {
        return $this->mc->add($key, $value, is_null($expire) ? $this->expire : $expire);
    }

    public function delete($key) {
        return $this->mc->delete($key);
    }

    public function set($key, $value, $expire = null) {
        return $this->mc->set($key, $value, is_null($expire) ? $this->expire : $expire);
    }

    public function get($key) {
        return $this->mc->get($key);
    }

    public function flush() {
        return $this->mc->flush();
    }
}