<?php
/**
 * Permission Engine — Central RBAC registry.
 *
 * Roles (least → most privileged):
 *   viewer  — view + add proposals only (read-mostly access)
 *   manager — full operational access: edit, delete, import, reclassify,
 *             accept/reject decisions, manage weights, AI autofill.
 *             CANNOT manage users — that is the one bit reserved for admin.
 *   admin   — everything manager can do PLUS user management (create /
 *             edit / delete users, change roles).
 *
 * To add a new permission:  add a key to PERMISSIONS with the allowed roles array.
 * To add a new role:        add it to the relevant permission arrays here — no other file changes needed.
 */
class Permission {

    /**
     * Map of permission key → roles that have it.
     * The FIRST role listed is the minimum required.
     */
    private const PERMISSIONS = [
        // ── Proposals ──────────────────────────────────────────────────────────
        'view_proposals'       => ['viewer', 'manager', 'admin'],
        'add_proposals'        => ['viewer', 'manager', 'admin'],
        'edit_proposals'       => ['manager', 'admin'],
        'delete_proposals'     => ['manager', 'admin'],
        'import_proposals'     => ['viewer', 'manager', 'admin'],
        'reclassify_proposals' => ['manager', 'admin'],

        // ── Decisions (Accept / Reject) ─────────────────────────────────────
        'decide_proposals'     => ['manager', 'admin'],

        // ── Configuration ───────────────────────────────────────────────────
        'manage_weights'       => ['manager', 'admin'],

        // ── Administration (admin-only — this is what distinguishes admin from manager) ──
        'manage_users'         => ['admin'],
    ];

    /**
     * Check if the currently logged-in user has the given permission.
     */
    public static function can(string $permission): bool {
        $role = TokenAuth::role(); // 'viewer' | 'manager' | 'admin'
        $allowed = self::PERMISSIONS[$permission] ?? [];
        return in_array($role, $allowed, true);
    }

    /**
     * Return the full permission map (useful for debug/admin views).
     */
    public static function all(): array {
        return self::PERMISSIONS;
    }

    /**
     * Return all permissions a given role has.
     */
    public static function forRole(string $role): array {
        $perms = [];
        foreach (self::PERMISSIONS as $key => $roles) {
            if (in_array($role, $roles, true)) {
                $perms[] = $key;
            }
        }
        return $perms;
    }
}
