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
 * Represents an user on Discord.
 *
 * @property int                                                  $id                 The user ID.
 * @property string                                               $username           The username.
 * @property string                                               $discriminator      The discriminator of this user.
 * @property boolean                                              $bot                Is the user a bot? Or are you a bot?
 * @property string|null                                          $avatar             The hash of the user's avatar, or null.
 * @property string                                               $email              An email address or maybe nothing at all. More likely to be nothing at all.
 * @property boolean|null                                         $mfaEnabled         Whether the user has two factor enabled on their account, or null if no information provided.
 * @property boolean|null                                         $verified           Whether the email on this account has been verified, or null if no information provided.
 * @property boolean                                              $webhook            Determines wether the user is a webhook or not.
 * @property int                                                  $createdTimestamp   The timestamp of when this user was created.
 *
 * @property \DateTime                                            $createdAt          An DateTime instance of the createdTimestamp.
 * @property int                                                  $defaultAvatar      DEPRECATED: The identifier of the default avatar for this user.
 * @property \CharlotteDunois\Yasmin\Models\DMChannel|null        $dmChannel          DEPRECATED: The DM channel for this user, if it exists, or null.
 * @property \CharlotteDunois\Yasmin\Models\Message|null          $lastMessage        DEPRECATED (with no replacement): The last message the user sent while the client was online, or null.
 * @property \CharlotteDunois\Yasmin\Models\Presence|null         $presence           DEPRECATED: The presence for this user, or null.
 * @property string                                               $tag                Username#Discriminator.
 */
class User extends ClientBase {
    /**
     * The user ID.
     * @var int
     */
    protected $id;
    
    /**
     * The username.
     * @var string
     */
    protected $username;
    
    /**
     * The discriminator of this user.
     * @var string
     */
    protected $discriminator;
    
    /**
     * Is the user a bot? Or are you a bot?
     * @var bool
     */
    protected $bot;
    
    /**
     * The hash of the user's avatar, or null.
     * @var string|null
     */
    protected $avatar;
    
    /**
     * An email address or maybe nothing at all. More likely to be nothing at all.
     * @var string
     */
    protected $email;
    
    /**
     * Whether the user has two factor enabled on their account, or null if no information provided.
     * @var bool|null
     */
    protected $mfaEnabled;
    
    /**
     * Whether the email on this account has been verified, or null if no information provided.
     * @var bool|null
     */
    protected $verified;
    
    /**
     * Determines wether the user is a webhook or not.
     * @var bool
     */
    protected $webhook;
    
    /**
     * The timestamp of when this user was created.
     * @var int
     */
    protected $createdTimestamp;
    
    /**
     * DEPRECATED: The last ID of the message the user sent while the client was online, or null.
     * @var int|null
     */
    public $lastMessageID;
    
    /**
     * Whether the user fetched this user.
     * @var bool
     */
    protected $userFetched = false;
    
    /**
     * @internal
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, array $user, bool $isWebhook = false, bool $userFetched = false) {
        parent::__construct($client);
        
        $this->id = (int) $user['id'];
        $this->webhook = $isWebhook;
        $this->userFetched = $userFetched;
        
        $this->createdTimestamp = (int) \CharlotteDunois\Yasmin\Utils\Snowflake::deconstruct($this->id)->timestamp;
        $this->_patch($user);
    }
    
    /**
     * {@inheritdoc}
     * @return mixed
     * @throws \RuntimeException
     * @internal
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        switch($name) {
            case 'createdAt':
                return \CharlotteDunois\Yasmin\Utils\DataHelpers::makeDateTime($this->createdTimestamp);
            break;
            case 'defaultAvatar': // TODO: DEPRECATED
                return ($this->discriminator % 5);
            break;
            case 'dmChannel': // TODO: DEPRECATED
                $channel = $this->client->get()->channels->first(function ($channel) {
                    return ($channel->type === 'dm' && $channel->isRecipient($this));
                });
                
                return $channel;
            break;
            case 'lastMessage': // TODO: DEPRECATED
                if($this->lastMessageID !== null) {
                    $channel = $this->client->get()->channels->first(function ($channel) {
                        return ($channel->type === 'text' && $channel->messages->has($this->lastMessageID));
                    });
                    
                    if($channel) {
                        return $channel->messages->get($this->lastMessageID);
                    }
                }
                
                return null;
            break;
            case 'presence': // TODO: DEPRECATED
                return $this->getPresence();
            break;
            case 'tag':
                return $this->username.'#'.$this->discriminator;
            break;
        }
        
        return parent::__get($name);
    }
    
    /**
     * @return mixed
     * @internal
     */
    function __debugInfo() {
        $vars = parent::__debugInfo();
        unset($vars['userFetched']);
        return $vars;
    }
    
    /**
     * Opens a DM channel to this user. Resolves with an instance of DMChannel.
     * @return \React\Promise\ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\DMChannel
     */
    function createDM() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $channel = $this->client->get()->channels->first(function ($channel) {
                return ($channel->type === 'dm' && $channel->isRecipient($this));
            });
            
            if($channel) {
                return $resolve($channel);
            }
            
            $this->client->get()->apimanager()->endpoints->user->createUserDM($this->id)->done(function ($data) use ($resolve) {
                $channel = $this->client->get()->channels->factory($data);
                $resolve($channel);
            }, $reject);
        }));
    }
    
    /**
     * Deletes an existing DM channel to this user. Resolves with $this.
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function deleteDM() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $channel = $this->client->get()->channels->first(function ($channel) {
                return ($channel->type === 'dm' && $channel->isRecipient($this));
            });
            
            if(!$channel) {
                return $resolve($this);
            }
            
            $this->client->get()->apimanager()->endpoints->channel->deleteChannel($channel->id)->done(function () use ($channel, $resolve) {
                $this->client->get()->channels->delete($channel->id);
                $resolve($this);
            }, $reject);
        }));
    }
    
    /**
     * Get the default avatar URL.
     * @param int|null  $size    Any powers of 2 (16-2048).
     * @param string    $format  One of png, webp, jpg or gif (empty = default format).
     * @return string
     * @throws \InvalidArgumentException
     */
    function getDefaultAvatarURL(?int $size = 1024, string $format = '') {
        if($size & ($size - 1)) {
            throw new \InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }
        
        if(empty($format)) {
            $format = $this->getAvatarExtension();
        }
        
        return \CharlotteDunois\Yasmin\HTTP\APIEndpoints::CDN['url'].\CharlotteDunois\Yasmin\HTTP\APIEndpoints::format(\CharlotteDunois\Yasmin\HTTP\APIEndpoints::CDN['defaultavatars'], ($this->discriminator % 5), $format).(!empty($size) ? '?size='.$size : '');
    }
    
    /**
     * Get the avatar URL.
     * @param int|null  $size    Any powers of 2 (16-2048).
     * @param string    $format  One of png, webp, jpg or gif (empty = default format).
     * @return string|null
     */
    function getAvatarURL(?int $size = 1024, string $format = '') {
        if($size & ($size - 1)) {
            throw new \InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }
        
        if(!$this->avatar) {
            return null;
        }
        
        if(empty($format)) {
            $format = $this->getAvatarExtension();
        }
        
        return \CharlotteDunois\Yasmin\HTTP\APIEndpoints::CDN['url'].\CharlotteDunois\Yasmin\HTTP\APIEndpoints::format(\CharlotteDunois\Yasmin\HTTP\APIEndpoints::CDN['avatars'], $this->id, $this->avatar, $format).(!empty($size) ? '?size='.$size : '');
    }
    
    /**
     * Get the URL of the displayed avatar.
     * @param int|null  $size    Any powers of 2 (16-2048).
     * @param string    $format  One of png, webp, jpg or gif (empty = default format).
     * @return string
     */
    function getDisplayAvatarURL(?int $size = 1024, string $format = '') {
        if($size & ($size - 1)) {
            throw new \InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }
        
        return ($this->avatar ? $this->getAvatarURL($size, $format) : $this->getDefaultAvatarURL($size));
    }
    
    /**
     * Gets the presence for this user, or null.
     * @return \CharlotteDunois\Yasmin\Models\Presence|null
     */
    function getPresence() {
        if($this->client->get()->presences->has($this->id)) {
            return $this->client->get()->presences->get($this->id);
        }
        
        foreach($this->client->get()->guilds as $guild) {
            if($guild->presences->has($this->id)) {
                $presence = $guild->presences->get($this->id);
                $this->client->get()->presences->set($this->id, $presence);
                
                return $presence;
            }
        }
        
        return null;
    }
    
    /**
     * Fetches the User's connections. Requires connections scope. Resolves with a Collection of UserConnection instances, mapped by their ID.
     * @param string  $accessToken
     * @return \React\Promise\ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\UserConnection
     */
    function fetchUserConnections(string $accessToken) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($accessToken) {
            $this->client->get()->apimanager()->endpoints->user->getUserConnections($accessToken)->done(function ($data) use ($resolve) {
                $collect = new \CharlotteDunois\Yasmin\Utils\Collection();
                foreach($data as $conn) {
                    $connection = new \CharlotteDunois\Yasmin\Models\UserConnection($this->client->get(), $this, $conn);
                    $collect->set($connection->id, $connection);
                }
                
                $resolve($collect);
            }, $reject);
        }));
    }
    
    /**
     * Automatically converts the User instance to a mention.
     * @return string
     */
    function __toString() {
        return '<@'.$this->id.'>';
    }
    
    /**
     * @return void
     * @internal
     */
    function _patch(array $user) {
        $this->username = (string) $user['username'];
        $this->discriminator = (string) ($user['discriminator'] ?? '0000');
        $this->bot = (!empty($user['bot']));
        $this->avatar = $user['avatar'] ?? null;
        $this->email = (string) ($user['email'] ?? '');
        $this->mfaEnabled = (isset($user['mfa_enabled']) ? !empty($user['mfa_enabled']) : null);
        $this->verified = (isset($user['verified']) ? !empty($user['verified']) : null);
    }
    
    /**
     * Returns default extension for the avatar.
     * @return string
     */
    protected function getAvatarExtension() {
        return (strpos($this->avatar, 'a_') === 0 ? 'gif' : 'png');
    }
}
