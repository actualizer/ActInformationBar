// act_information_bar_translation:* is intentionally omitted: AclWriteValidator and
// ApiController::validateAclPermissions() remap every translation command/read to the
// parent act_information_bar entity, so those privileges are never actually checked.
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'act_information_bar',
    roles: {
        viewer: {
            privileges: [
                'act_information_bar:read',
                'sales_channel:read',
                'language:read',
                'system_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'act_information_bar:update',
                // SystemConfigController requires update, create AND delete together on its
                // write route; without delete, saving the defaults page 403s for non-admins.
                'system_config:update',
                'system_config:create',
                'system_config:delete',
            ],
            dependencies: ['act_information_bar.viewer'],
        },
        creator: {
            privileges: [
                'act_information_bar:create',
            ],
            dependencies: ['act_information_bar.viewer', 'act_information_bar.editor'],
        },
        deleter: {
            privileges: [
                'act_information_bar:delete',
            ],
            dependencies: ['act_information_bar.viewer'],
        },
    },
});
