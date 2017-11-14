<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Represents the Client User.
 * @todo Implementation
 */
class ClientUser extends User {
    /**
     * @var array
     * @internal
     */
    protected $clientPresence;
    
    /**
     * WS Presence Update ratelimit 5/60s.
     * @var int
     * @internal
     */
    protected $firstPresence;
    
    /**
     * @var int
     * @internal
     */
    protected $firstPresenceCount = 0;
    
    /**
     * @param \CharlotteDunois\Yasmin\Client $client
     * @param array                          $user
     * @internal
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, $user) {
        parent::__construct($client, $user);
        
        $presence = $this->client->getOption('ws.presence', array());
        $this->clientPresence = array(
            'afk' => (isset($presence['afk']) ? (bool) $presence['afk'] : false),
            'since' => (!empty($presence['since']) ? (int) $presence['since'] : null),
            'status' => (!empty($presence['status']) ? $presence['status'] : 'online'),
            'game' => (!empty($presence['game']) ? $presence['game'] : null)
        );
    }
    
    /**
     * @inheritDoc
     *
     * @throws \Exception
     * @internal
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        return parent::__get($name);
    }
    
    /**
     * @internal
     */
    function __debugInfo() {
        $vars = parent::__debugInfo();
        unset($vars['clientPresence'], $vars['firstPresence'], $vars['firstPresenceCount']);
        return $vars;
    }
    
    /**
     * Set your avatar. Resolves with $this.
     * @param string $avatar  An URL or the filepath or the data.
     * @return \React\Promise\Promise
     */
    function setAvatar(string $avatar) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($avatar) {
            \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveFileResolvable($avatar)->then(function ($data) use ($resolve, $reject) {
                $image = \CharlotteDunois\Yasmin\Utils\DataHelpers::makeBase64URI($data);
                
                $this->client->apimanager()->endpoints->user->modifyCurrentUser(array('avatar' => $image))->then(function () use ($resolve) {
                    $resolve($this);
                }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Set your username. Resolves with $this.
     * @param string $username
     * @return \React\Promise\Promise
     */
    function setUsername(string $username) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($username) {
            $this->client->apimanager()->endpoints->user->modifyCurrentUser(array('username' => $username))->then(function () use ($resolve) {
                $resolve($this);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Set your status. Resolves with $this.
     * @param string $status  Valid values are: online, idle, dnd and offline.
     * @return \React\Promise\Promise
     */
    function setStatus(string $status) {
        $presence = array(
            'status' => $status
        );
        
        return $this->setPresence($presence);
    }
    
    /**
     * Set your playing game. Resolves with $this.
     * @param string       $name  The game name.
     * @param string|void  $url   If you're streaming, this is the url to the stream.
     * @return \React\Promise\Promise
     */
    function setGame(string $name, string $url = '') {
        $presence = array(
            'game' => array(
                'name' => $name,
                'type' => 0,
                'url' => null
            )
        );
        
        if(!empty($url)) {
            $presence['game']['type'] = 1;
            $presence['game']['url'] = $url;
        }
        
        return $this->setPresence($presence);
    }
    
    /**
     * Set your presence. Resolves with $this.
     *
     *  $presence = array(
     *      'afk' => bool,
     *      'since' => integer|null,
     *      'status' => string,
     *      'game' => array(
     *          'name' => string,
     *          'type' => int,
     *          'url' => string|null
     *      )|null
     *  )
     *
     *  Any field in the first dimension is optional and will be automatically filled with the last known value. Throws because fuck you and your spamming attitude. Ratelimit is 5/60s.
     *
     * @param array $presence
     * @return \React\Promise\Promise
     * @throws \BadMethodCallException
     */
    function setPresence(array $presence) {
        if(empty($presence)) {
            return \React\Promise\reject(new \InvalidArgumentException('Presence argument can not be empty'));
        }
        
        if($this->firstPresence > (\time() - 60)) {
            if($this->firstPresenceCount >= 5) {
                throw new \BadMethodCallException('Stop spamming setPresence you idiot');
            }
            
            $this->firstPresenceCount++;
        } else {
            $this->firstPresence = \time();
            $this->firstPresenceCount = 1;
        }
        
        $packet = array(
            'op' => \CharlotteDunois\Yasmin\Constants::OPCODES['STATUS_UPDATE'],
            'd' => array(
                'afk' => (!empty($presence['afk']) ? $presence['afk'] : $this->clientPresence['afk']),
                'since' => (!empty($presence['since']) ? $presence['since'] : $this->clientPresence['since']),
                'status' => (!empty($presence['status']) ? $presence['status'] : $this->clientPresence['status']),
                'game' => (!empty($presence['game']) ? $presence['game'] : $this->clientPresence['game'])
            )
        );
        
        $this->clientPresence = $packet['d'];
        
        $presence = $this->presence;
        if($presence) {
            $presence->_patch($this->clientPresence);
        }
        
        return $this->client->wsmanager()->send($packet)->then(function () {
            return $this;
        });
    }
    
    /**
     * Creates a new Group DM with the owner of the access tokens. Resolves with an instance of GroupDMChannel. The structure of the array is as following:
     *
     *  array(
     *      'accessToken' => \CharlotteDunois\Yasmin\Models\User|string (user ID)
     *  )
     *
     * The nicks array is an associative array of userID => nick. The nick defaults to the username.
     *
     * @param array  $userWithAccessTokens
     * @param array  $nicks
     * @return \React\Promise\Promise
     * @see \CharlotteDunois\Yasmin\Models\GroupDMChannel
     */
    function createGroupDM(array $userWithAccessTokens, array $nicks = array()) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($nicks, $userWithAccessTokens) {
            $tokens = array();
            $users = array();
            
            foreach($userWithAccessTokens as $token => $user) {
                $user = $this->client->users->resolve($user);
                
                $tokens[] = $token;
                $users[$user->id] = (!empty($nicks[$user->id]) ? $nicks[$user->id] : $user->username);
            }
            
            $this->client->apimanager()->endpoints->user->createGroupDM($tokens, $users)->then(function ($data) use ($resolve) {
                $channel = $this->client->channels->factory($data);
                $resolve($channel);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
}