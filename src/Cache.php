<?php
namespace bhw\BhawanaCore;

use bhw\BhawanaCore\Memcached;

class Cache {

    private static $_instance;

    private $ci;
    private $cch;
    private $key;
    private $duration;

    public function __construct() {
        $ci = &\get_instance();
        $this->ci = $ci;
		$ci->load->driver('cache');
        if ($ci->cache->memcached->is_supported()) {
			$this->cch = $ci->cache->memcached;
		} else {
            $this->cch = $ci->cache->file;
        }
        $this->duration = defined('CACHE_DURATION') ? CACHE_DURATION : 60;
    }

    public function load() {
		bh_log(['LOAD CACHE', $this->key, $this->ci->cache->memcached->is_supported()]);
        return $this->cch->get($this->key);
    }

    public function save($value) {
		bh_log(['SAVE CACHE', $this->key, $this->ci->cache->memcached->is_supported()]);
        return $this->cch->save($this->key, $value, $this->duration);
    }

    public function key($key) {
		$key = preg_replace('/[^\da-z\.\_]/i', '', $key);
		$key = strlen($key) <= 225 ? $key : substr($key, 0, 225);
        $this->key = $key;
    }

    public static function instance($key = null) {
        if (!static::$_instance)
            static::$_instance = new Cache();
        
        (static::$_instance)->key($key);
        return static::$_instance;
    }

}
