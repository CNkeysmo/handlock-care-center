<?php
namespace HLCC\Core;

if (!defined('ABSPATH')) exit;

final class Capabilities {
    const ROLE_CUSTOMER = 'hlcc_customer';

    public static function register_roles(): void {
        // Add customer role if missing
        if (!get_role(self::ROLE_CUSTOMER)) {
            add_role(self::ROLE_CUSTOMER, '护理中心客户', [
                'read' => true,
            ]);
        }
    }
}
