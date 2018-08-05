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
     * {@inheritdoc}
     *
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
     * @inheritDoc
     */
    function has($key) {
        if(\is_array($key) || \is_object($key)) {
            return false;
        }
        
        return parent::has(((int) $key));
    }
    
    /**
     * @inheritDoc
     */
    function get($key) {
        if(\is_array($key) || \is_object($key)) {
            return null;
        }
        
        return parent::get(((int) $key));
    }
    
    /**
     * @inheritDoc
     */
    function set($key, $val) {
        if(\is_array($key) || \is_object($key)) {
            throw new \InvalidArgumentException('Key can not be an array or object');
        }
        
        return parent::set(((int) $key), $val);
    }
    
    /**
     * @internal
     */
    function serialize() {
        $vars = \get_object_vars($this);
        unset($vars['client'], $vars['timer']);
        
        return \serialize($vars);
    }
    
    /**
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
     */
    function set($key, $value) {
        parent::set($key, $value);
        
        if(static::$emitUpdates) {
            $this->client->emit('internal.storage.set', $this, $key, $value);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    function delete($key) {
        parent::delete($key);
        
        if(static::$emitUpdates) {
            $this->client->emit('internal.storage.delete', $this, $key);
        }
    }
}
