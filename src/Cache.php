<?php
namespace bhw\BhawanaCore;

use bhw\BhawanaCore\Memcached;

class Cache {

    private static $_instance;

    private $cch;
    private $key;
    private $duration;

    public function __construct() {
        $ci = &\get_instance();
		$ci->load->driver('cache');
        if ($ci->cache->memcached->is_supported()) {
			$this->cch = $ci->cache->memcached;
		} else {
            $this->cch = $ci->cache->file;
        }
        $this->duration = defined(CACHE_DURATION) ? CACHE_DURATION : 180;
    }

    public function load() {
        return $this->cch->get($this->key);
    }

    public function save($value) {
        return $this->cch->save($this->key, $value, $this->duration);
    }

    public function key($key) {
        $this->key = $key;
    }

    public static function instance($key = null) {
        if (!static::$_instance)
            static::$_instance = new Cache();
        
        (static::$_instance)->key = $key;
        return static::$_instance;
    }

}
