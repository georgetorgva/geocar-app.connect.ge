<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App;
use Illuminate\Http\Request;
use App\Services\User\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\Gate;
use Illuminate\Support\Facades\DB;

class RolesController extends Controller
{
    public function index(RoleService $roleService)
    {
        if (Gate::denies('viewRoles')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }
        /*DB::enableQueryLog();*/
        $roles =  $roleService->getRoles();
        $permissions =  $roleService->getPermissions();

        /*dump(\DB::getQueryLog());*/
        return view('CMS.Pages.Users.Roles.roles', compact('roles', 'permissions'));
    }

    public function getRole(Request $request, RoleService $roleService)
    {
        return $roleService->getRole($request->id);
    }

    public function getRoleWithPermission(Request $request, RoleService $roleService)
    {
        $id = $request->id;
        $roles =  $roleService->getRoleWithPermission($id);
        return $roles;
    }

    public function changepermissions(Request $request, RoleService $roleService)
    {
        if (Gate::denies('changeRolePermissions')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }
        if (!$request->role_id) {
            session()->flash('error', tr('unknown role id'));
            return redirect()->back();
        }
        $permissionToRole =  $roleService->permissionToRole($request->role_id, $request->permissions);
        if ($permissionToRole) {
            session()->flash('success', tr('permission successfully assigned to role'));
            return redirect()->back();
        } else {
            session()->flash('error', 'Unknown Error!');
            return redirect()->back();
        }
    }

    public function createRole(Request $request, RoleService $roleService)
    {
        if (Gate::denies('createRoles')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }


        $valid = [
            'name' =>'required|string|unique:roles',
            'guard_name' =>'required',
            'has_backend_access' =>'required',
            'protected' =>'required',
            'for_registration' =>'required',
        ];
        request()->validate($valid);
        $data = [
            'name' => $request->name,
            'guard_name' => $request->guard_name,
            'has_backend_access' => $request->has_backend_access,
            'protected' => $request->protected,
            'for_registration' => $request->for_registration,

        ];
        $role =  $roleService->createRole($data);

        if ($role) {
            session()->flash('success', tr('role successfully created'));
            return redirect()->back();
        } else {
            session()->flash('error', 'Unknown Error!');
            return redirect()->back();
        }
    }


    public function createPermission(Request $request, RoleService $roleService)
    {
        if (Gate::denies('createPermission')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }
        $valid = [
            'permission' =>'required|string|unique:permissions,name',
            'can' =>'required|string',
            'module' =>'required|string',
            'guard' =>'required'
        ];
        request()->validate($valid);
        $data = [
            'name' => $request->permission,
            'can' => $request->can,
            'module' => $request->module,
            'guard_name' => $request->guard,
        ];

        $permission =  $roleService->createPermission($data);

        if ($permission) {
            session()->flash('success', tr('permission successfully created'));
            return redirect()->back();
        } else {
            session()->flash('error', 'Unknown Error!');
            return redirect()->back();
        }
    }


    public function updateRole(Request $request, RoleService $roleService)
    {
        if (Gate::denies('updateRoles')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }
        $valid = [
            'name' =>'required|string',
            'guard_name' =>'required'
        ];
        request()->validate($valid);
        $data = [
            'name' => $request->name,
            'guard_name' => $request->guard_name,
            'has_backend_access' => $request->has_backend_access,
            'protected' => $request->protected,
            'for_registration' => $request->for_registration,
        ];
        $role =  $roleService->updateRole($request->role_id, $data);
        if ($role) {
            session()->flash('success', tr('role successfully updated'));
            return redirect()->back();
        } else {
            session()->flash('error', 'Unknown Error!');
            return redirect()->back();
        }
    }

    public function deleteRole(Request $request, RoleService $roleService)
    {
        if (Gate::denies('deleteRoles')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }
        $role =  $roleService->deleteRole($request->id);
        if ($role) {
            session()->flash('success', tr('role successfully deleted'));
            return redirect()->back();
        } else {
            session()->flash('error', 'Unknown Error!');
            return redirect()->back();
        }
    }

    public function getUserRole(Request $request, RoleService $roleService)
    {
        $roles = $roleService->getUserRole($request->id);
        return $roles;
    }

    public function assignRoleToUser(Request $request, RoleService $roleService)
    {
        if (Gate::denies('assignRole')) {
            session()->flash('error', tr('you dont have permission To Access!'));
            return redirect()->route('CMS.Dashboard');
        }


        $roleService->assignRole($request->user_id, $request->roles);


        return redirect()->back();
    }
}
