<?php
// Approve a user's 2026 audit the way the reviewer does, which completes the course and issues the certificate.
//   php approve.php <user_login> <admin_login>
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';
$u = get_user_by('login', $argv[1] ?? '');
$a = get_user_by('login', $argv[2] ?? 'Panwar-education');
if (!$u || !$a) { fwrite(STDERR, "user or admin not found\n"); exit(1); }
wp_set_current_user($a->ID);
$ok = hcp_mca_approve((int) $u->ID, (int) $a->ID, 'v2');
$v = hcp_mca_variants()['v2'];
echo ($ok ? 'approved' : 'NOT approved') . " {$u->user_login}; course " . $v['course'] . ' complete=' . (learndash_course_completed((int) $u->ID, (int) $v['course']) ? 'yes' : 'no') . "\n";
