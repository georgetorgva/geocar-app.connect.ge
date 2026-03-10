<?php

namespace App\Services\User;

use App\Services\MainService;
use App\Models\User\User;
use http\Env\Request;
use App;

class RoleService extends MainService
{
    protected $rolesRepository;

    public function __construct($rolesRepository)
    {
        $this->rolesRepository = $rolesRepository;
    }


    public function getRoles()
    {
        $roles =  $this->rolesRepository->getRoles();
        return $roles;
    }

    public function getPermissions()
    {
        $permissions =  $this->rolesRepository->getPermissions();
        return $permissions;
    }

    public function getRoleWithPermission($id)
    {
        return $this->rolesRepository->getRoleWithPermission($id);
    }

    public function getRole($id)
    {
        return $this->rolesRepository->getRole($id);
    }

    public function permissionToRole($roleId, $permissions)
    {
        $role =  $this->getRoleWithPermission($roleId);
        return $role->syncPermissions($permissions);
    }

    public function createRole($data)
    {
        $role =  $this->rolesRepository->createRole($data);
        return $role;
    }


    public function createPermission($data)
    {
        $permission =  $this->rolesRepository->createPermission($data);
        return $permission;
    }


    public function updateRole($id, $data)
    {
        $role =  $this->rolesRepository->updateRole($id, $data);
        return $role;
    }

    public function deleteRole($id)
    {
        $role =  $this->rolesRepository->deleteRole($id);
        return $role;
    }

    public function getUserRole($id)
    {
        return $this->rolesRepository->getUserRole($id);
    }

    public function assignRole(int $id, ?array $data)
    {
        $user = App::make(UserService::class)->get($id);
        $user->syncRoles($data);
    }
}
