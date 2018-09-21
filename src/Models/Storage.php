<?php
/**
 * Yasmin
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Base class for all storages.
 */
class Storage extends \CharlotteDunois\Yasmin\Utils\Collection
    implements \CharlotteDunois\Yasmin\Interfaces\StorageInterface, \Serializable {
    
    /**
     * The client this storage belongs to.
     * @var \CharlotteDunois\Yasmin\Client
     */
    protected $client;
    
    /**
     * Tells the storages to emit `internal.storage.set` and `internal.storage.delete` events.
     * @var bool
     */
    public static $emitUpdates = false;
    
    /**
     * @internal
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, array $data = null) {
        parent::__construct($data);
        $this->client = $client;
    }
    
    /**
     * @param string  $name
     * @return bool
     * @throws \Exception
     * @internal
     */
    function __isset($name) {
        try {
            return $this->$name !== null;
        } catch (\RuntimeException $e) {
            if($e->getTrace()[0]['function'] === '__get') {
                return false;
            }
            
            throw $e;
        }
    }
    
    /**
     * @param string  $name
     * @return string
     * @throws \RuntimeException
     * @internal
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        throw new \RuntimeException('Unknown property '.\get_class($this).'::$'.$name);
    }
    
    /**
     * @return string
     * @internal
     */
    function serialize() {
        $vars = \get_object_vars($this);
        unset($vars['client'], $vars['timer']);
        
        return \serialize($vars);
    }
    
    /**
     * @return void
     * @internal
     */
    function unserialize($data) {
        if(\CharlotteDunois\Yasmin\Models\ClientBase::$serializeClient === null) {
            throw new \Exception('Unable to unserialize a class without ClientBase::$serializeClient being set');
        }
        
        $data = \unserialize($data);
        foreach($data as $name => $val) {
            $this->$name = $val;
        }
        
        $this->client = \CharlotteDunois\Yasmin\Models\ClientBase::$serializeClient;
    }
    
    /**
     * {@inheritdoc}
     * @return bool
     * @throws \InvalidArgumentException
     */
    function has($key) {
        if(\is_array($key) || \is_object($key)) {
            throw new \InvalidArgumentException('Key can not be an array or object');
        }
        
        $key = (int) $key;
        return parent::has($key);
    }
    
    /**
     * {@inheritdoc}
     * @return mixed|null
     * @throws \InvalidArgumentException
     */
    function get($key) {
        if(\is_array($key) || \is_object($key)) {
            throw new \InvalidArgumentException('Key can not be an array or object');
        }
        
        $key = (int) $key;
        return parent::get($key);
    }
    
    /**
     * {@inheritdoc}
     * @return $this
     * @throws \InvalidArgumentException
     */
    function set($key, $value) {
        if(\is_array($key) || \is_object($key)) {
            throw new \InvalidArgumentException('Key can not be an array or object');
        }
        
        $key = (int) $key;
        parent::set($key, $value);
        
        if(static::$emitUpdates) {
            $this->client->emit('internal.storage.set', $this, $key, $value);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @return $this
     * @throws \InvalidArgumentException
     */
    function delete($key) {
        if(\is_array($key) || \is_object($key)) {
            throw new \InvalidArgumentException('Key can not be an array or object');
        }
        
        $key = (int) $key;
        parent::delete($key);
        
        if(static::$emitUpdates) {
            $this->client->emit('internal.storage.delete', $this, $key);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @return \CharlotteDunois\Yasmin\Interfaces\StorageInterface
     */
    function copy() {
        return (new static($this->client, $this->data));
    }
    
    /**
     * {@inheritdoc}
     * @param callable  $closure
     * @return \CharlotteDunois\Yasmin\Interfaces\StorageInterface
    */
    function filter(callable $closure) {
        $col = parent::filter($closure);
        return (new static($this->client, $col->all()));
    }
    
    /**
     * {@inheritdoc}
     * @param callable    $closure
     * @param int         $options
     * @return \CharlotteDunois\Yasmin\Interfaces\StorageInterface
    */
    function sort(?callable $closure = null, $options = SORT_REGULAR) {
        $col = parent::sort($closure, $options);
        return (new static($this->client, $col->all()));
    }
    
    /**
     * {@inheritdoc}
     * @param mixed|callable  $sortkey
     * @param int             $options
     * @param bool            $descending
     * @return \CharlotteDunois\Yasmin\Interfaces\StorageInterface
    */
    function sortBy($sortkey, $options = \SORT_REGULAR, bool $descending = false) {
        $col = parent::sortBy($sortkey, $options, $descending);
        return (new static($this->client, $col->all()));
    }
    
    /**
     * {@inheritdoc}
     * @param mixed|\Closure  $sortkey
     * @param int             $options
     * @return \CharlotteDunois\Yasmin\Interfaces\StorageInterface
    */
    function sortByDesc($sortkey, $options = \SORT_REGULAR) {
        $col = parent::sortByDesc($sortkey, $options);
        return (new static($this->client, $col->all()));
    }
}
