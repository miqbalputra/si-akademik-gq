<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

trait HasRoleBasedResourceAccess
{
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserHasAnyRole(static::viewRoles());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && static::policyAllows('view', $record);
    }

    public static function canCreate(): bool
    {
        return static::currentUserHasAnyRole(static::manageRoles());
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate() && static::policyAllows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate() && static::policyAllows('delete', $record);
    }

    /**
     * Consult the model's registered policy for a per-record ability when one
     * is defined. Resources without a policy (or whose policy does not define
     * the given ability) keep the role-only behaviour from {@see canCreate()}
     * / {@see canViewAny()} above. This restores per-record policy enforcement
     * (e.g. DiniyyahScore "locked" guard, ReportCard guardian ownership) that
     * the role-only overrides would otherwise shadow.
     */
    protected static function policyAllows(string $ability, Model $record): bool
    {
        $policy = Gate::getPolicyFor($record);

        if ($policy === null) {
            return true;
        }

        if (! method_exists($policy, $ability)) {
            return true;
        }

        return Gate::forUser(Auth::user())->allows($ability, $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function canRestore(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canRestoreAny(): bool
    {
        return static::canCreate();
    }

    public static function canReorder(): bool
    {
        return static::canCreate();
    }

    public static function canReplicate(Model $record): bool
    {
        return static::canCreate();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::constantValue('NAVIGATION_GROUP') ?? parent::getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return static::constantValue('NAVIGATION_LABEL') ?? parent::getNavigationLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return static::constantValue('NAVIGATION_SORT') ?? parent::getNavigationSort();
    }

    protected static function currentUserCanManageResource(): bool
    {
        return static::canCreate();
    }

    protected static function currentUserHasAnyRole(array $roles): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    protected static function viewRoles(): array
    {
        return static::constantValue('VIEW_ROLES') ?? ['admin'];
    }

    protected static function manageRoles(): array
    {
        return static::constantValue('MANAGE_ROLES') ?? ['admin'];
    }

    protected static function constantValue(string $name): mixed
    {
        $reflection = new \ReflectionClass(static::class);

        return $reflection->hasConstant($name) ? $reflection->getConstant($name) : null;
    }
}
