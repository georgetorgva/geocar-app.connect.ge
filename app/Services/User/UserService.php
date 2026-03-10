<?php

namespace App\Services\User;

use App\Domain\Exceptions\InvalidPermissionException;
use App\Services\MainService;
use App\Models\User\User;

class UserService extends MainService
{
    protected $userRepository;

    public function __construct($userRepository)
    {
        $this->userRepository = $userRepository;
    }


    public function create($data)
    {
        /*if(!$this->user->can('register')){
            throw new InvalidPermissionException("Register permission not found");
        }*/
        //User::get()
        $user = $this->userRepository->create($data);
        if (isset($data['avatar'])) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatars');
        }
        return response($user);
    }

    public function get($id)
    {
        $user = $this->userRepository->getUserById($id);
        return $user;
    }

    public function getByUsername($username)
    {
        $user = $this->userRepository->getUserByUsername($username);
        return $user;
    }

    public function update($id, $data)
    {
        $data = User::transformPostFields($data);

        $user = $this->userRepository->update($id, $data);
        if (isset($data['avatar'])) {
            $user->clearMediaCollection('avatars');
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatars');
        }
        return $user;
    }

    public function search(?string $keyword = '', ?int $page = 1, ?int $limit = 5)
    {
        $users = $this->userRepository->search($keyword, $page, $limit);
        return $users;
    }

    public function delete($id)
    {
        $user = $this->userRepository->delete($id);
        return $user;
    }
}
