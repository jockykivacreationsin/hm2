<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// user_roles: id, role
class UserRole extends Model
{
    protected $table = 'user_roles';
    public $timestamps = false;
    protected $fillable = ['role'];
    public function permissions() { return $this->hasMany(UserRolePermission::class, 'user_role_id'); }
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('permission', $permission)->exists();
    }
}
