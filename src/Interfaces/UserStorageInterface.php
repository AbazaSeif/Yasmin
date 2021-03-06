<?php
/**
 * Yasmin
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Interfaces;

/**
 * Something all user storages implement. The storage also is used as factory.
 */
interface UserStorageInterface extends StorageInterface {
    /**
     * Returns the current element. From Iterator interface.
     * @return \CharlotteDunois\Yasmin\Models\User
     */
    function current();
    
    /**
     * Fetch the key from the current element. From Iterator interface.
     * @return string
     */
    function key();
    
    /**
     * Advances the internal pointer. From Iterator interface.
     * @return \CharlotteDunois\Yasmin\Models\User|false
     */
    function next();
    
    /**
     * Resets the internal pointer. From Iterator interface.
     * @return \CharlotteDunois\Yasmin\Models\User|false
     */
    function rewind();
    
    /**
     * Checks if current position is valid. From Iterator interface.
     * @return bool
     */
    function valid();
    
    /**
     * Returns all items.
     * @return \CharlotteDunois\Yasmin\Models\User[]
     */
    function all();
    
    /**
     * Resolves given data to an user.
     * @param \CharlotteDunois\Yasmin\Models\User|\CharlotteDunois\Yasmin\Models\GuildMember|string|int  $user  string/int = user ID
     * @return \CharlotteDunois\Yasmin\Models\User
     * @throws \InvalidArgumentException
     */
    function resolve($user);
    
    /**
     * Patches an user (retrieves the user if the user exists), returns null if only the ID is in the array, or creates an user.
     * @param array  $user
     * @return \CharlotteDunois\Yasmin\Models\User|null
     */
    function patch(array $user);
    
    /**
     * Determines if a given key exists in the collection.
     * @param string  $key
     * @return bool
     * @throws \InvalidArgumentException
    */
    function has($key);
    
    /**
     * Returns the item at a given key. If the key does not exist, null is returned.
     * @param string  $key
     * @return \CharlotteDunois\Yasmin\Models\User|null
     * @throws \InvalidArgumentException
    */
    function get($key);
    
    /**
     * Sets a key-value pair.
     * @param string                               $key
     * @param \CharlotteDunois\Yasmin\Models\User  $value
     * @return $this
     * @throws \InvalidArgumentException
     */
    function set($key, $value);
    
    /**
     * Factory to create (or retrieve existing) users.
     * @param array  $data
     * @param bool   $userFetched
     * @return \CharlotteDunois\Yasmin\Models\User
     * @internal
     */
    function factory(array $data, bool $userFetched = false);
}
