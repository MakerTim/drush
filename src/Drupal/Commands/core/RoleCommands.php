<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\user\Entity\Role;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Utils\StringUtils;

class RoleCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Create a new role.
     *
     * @command role:create
     * @param $machine_name The symbolic machine name for the role.
     * @param $human_readable_name A descriptive name for the role.
     * @usage drush role:create 'test_role' 'Test role'
     *   Create a new role with a machine name of 'test_role', and a human-readable name of 'Test role'.
     * @aliases rcrt,role-create
     */
    public function create($machine_name, $human_readable_name = null)
    {
        $role = Role::create([
        'id' => $machine_name,
        'label' => $human_readable_name ?: ucfirst($machine_name),
        ], 'user_role');
        $role->save();
        $this->logger()->success(dt('Created "!role"', ['!role' => $machine_name]));
        return $role;
    }

    /**
     * Delete a new role.
     *
     * @command role:delete
     * @param $machine_name The symbolic machine name for the role.
     * @validate-entity-load user_role machine_name
     * @usage drush role:delete 'test_role'
     *   Delete the role 'test_role'.
     * @aliases rdel,role-delete
     */
    public function delete($machine_name): void
    {
        $role = Role::load($machine_name);
        $role->delete();
        $this->logger()->success(dt('Deleted "!role"', ['!role' => $machine_name]));
    }

    /**
     * Grant specified permission(s) to a role.
     *
     * @todo Add validation for permission names.
     *
     * @command role:perm:add
     * @validate-entity-load user_role machine_name
     * @validate-permissions permissions
     * @param $machine_name The role to modify.
     * @param $permissions The list of permission to grant, delimited by commas.
     * @usage  drush role:perm:add anonymous 'post comments'
     *   Allow anon users to post comments.
     * @usage drush role:perm:add anonymous 'post comments,access content'
     *   Allow anon users to post comments and access content.
     * @aliases rap,role-add-perm
     */
    public function roleAddPerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_grant_permissions($machine_name, $perms);
        $this->logger()->success(dt('Added "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache:rebuild');
    }

    /**
     * Remove specified permission(s) from a role.
     *
     * @command role:perm:remove
     * @validate-entity-load user_role machine_name
     * @validate-permissions permissions
     * @param $machine_name The role to modify.
     * @param $permissions The list of permission to grant, delimited by commas.
     * @usage drush role:remove-perm anonymous 'post comments,access content'
     *   Remove 2 permissions from anon users.
     * @aliases rmp,role-remove-perm
     */
    public function roleRemovePerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_revoke_permissions($machine_name, $perms);
        $this->logger()->success(dt('Removed "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache:rebuild');
    }

    /**
     * Display a list of all roles defined on the system.
     *
     * @command role:list
     * @usage drush role:list authenticated
     *   Display permissions for the 'authenticated' role.
     * @usage drush role:list --filter='administer nodes'
     *   Display a list of roles that have the 'administer nodes' permission assigned.
     * @usage drush role:list --filter='rid=anonymous'
     *   Display only the 'anonymous' role.
     * @aliases rls,role-list
     * @field-labels
     *   rid: ID
     *   label: Role Label
     *   perms: Permissions
     *
     * @filter-default-field perms
     */
    public function roleList($options = ['format' => 'yaml']): RowsOfFields
    {
        $rows = [];

        $roles = Role::loadMultiple();
        foreach ($roles as $role) {
            $rows[$role->id()] = [
                'rid' => $role->id(),
                'label' => $role->label(),
                'perms' => $role->getPermissions(),
            ];
        }
        $result = new RowsOfFields($rows);
        $result->addRendererFunction([$this, 'renderPermsCell']);
        return $result;
    }

    /*
     * Used in the unlikely event user specifies --format=table.
     */
    public function renderPermsCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode(',', $cellData);
        }
        return $cellData;
    }
}
