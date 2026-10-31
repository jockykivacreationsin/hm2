<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRolePermission extends Model
{
    protected $table = 'user_role_permissions';

    public $timestamps = false;

    protected $fillable = [
        'user_role_id',
        'permission',
    ];
}
