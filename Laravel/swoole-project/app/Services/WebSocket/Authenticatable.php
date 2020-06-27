<?php

namespace App\Services\WebSocket;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Trait Authenticatable
 * 用户认证相关功能
 */
trait Authenticatable
{
    protected $userId;

    /**
     * Login using current user.
     *
     * @param AuthenticatableContract $user
     * @return mixed
     */
    public function loginUsing(AuthenticatableContract $user)
    {
        return $this->loginUsingId($user->getAuthIdentifier());
    }

    /**
     * Login using current userId
     *
     * @param int $userId
     * @return mixed
     */
    public function loginUsingId($userId)
    {
        return $this->join(static::USER_PREFIX.$userId);
    }

    /**
     * Logout with current sender's fd.
     *
     * @return mixed
     */
    public function logout()
    {
        if (is_null($userId = $this->getUserId())) {
            return null;
        }
        return $this->leave(static::USER_PREFIX.$userId);
    }

    /**
     * Set multiple recepients fds by users
     *
     * @param $users
     * @return Authenticable
     */
    public function toUser($users)
    {
        $users = is_object($users) ? func_get_args() : $users;

        $userIds = array_map(function (AuthenticatableContract $user) {
            $this->checkUser($user);
            return $user->getAuthIdentifier();
        }, $users);
        return $this->toUserId($userIds);
    }

    /**
     * Set multiple recepient's fds by userIds
     *
     * @param mixed $userIds
     * @return $this
     */
    public function toUserId($userIds)
    {
        $userIds = is_string($userIds) || is_integer($userIds) ? func_get_args() : $userIds;

        foreach ($userIds as $userId) {
            $fds = $this->room->getClients(static::USER_PREFIX . $userId);
            $this->to($fds);
        }

        return $this;
    }

    /**
     * Get current auth user id by sender's fd
     */
    public function getUserId()
    {
        if(!is_null($this->userId)) {
            return $this->userId;
        }
        $rooms = $this->room->getRooms($this->getSender());
        foreach($rooms as $room) {
            if (count($explode = explode(static::USER_PREFIX, $room)) === 2) {
                $this->userId = $explode[1];
            }
        }
        return $this->userId;
    }

    /**
     * Check if a user is online by given userId
     *
     * @param integer $userId
     * @return boolean
     */
    public function isUserIdOnline($userId)
    {
        return !empty($this->room->getClients(static::USER_PREFIX.$userId));
    }

    /**
     * Check if user object implements AuthenticatableContract
     *
     * @param  $user
     * @return void
     */
    protected function checkUser($user)
    {
        if (!$user instanceof AuthenticatableContract) {
            throw new InvalidArgumentException('user object must implement'.AuthenticatableContract::class);
        }
    }
}
