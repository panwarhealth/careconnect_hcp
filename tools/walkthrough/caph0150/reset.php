<?php
// Wipe a user's 2026 Clinical Audit progress so the walk starts clean.
//   php reset.php <user_login>
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';
global $wpdb;
$u = get_user_by('login', $argv[1] ?? '');
if (!$u) { fwrite(STDERR, "no such user\n"); exit(1); }
$uid = (int) $u->ID;
$v = hcp_mca_variants()['v2'];
$course = (int) $v['course']; $lesson = (int) $v['lesson']; $quiz = (int) $v['quiz'];
$forms = [(int) $v['audit_form'], (int) $v['eval_form']];

$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}frm_items WHERE user_id = %d AND form_id IN (%d,%d)", $uid, $forms[0], $forms[1]));
foreach ($ids as $eid) { FrmEntry::destroy((int) $eid); }
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}learndash_user_activity WHERE user_id = %d AND (course_id = %d OR post_id IN (%d,%d,%d))", $uid, $course, $course, $lesson, $quiz));
$prog = get_user_meta($uid, '_sfwd-course_progress', true);
if (is_array($prog) && isset($prog[$course])) { unset($prog[$course]); update_user_meta($uid, '_sfwd-course_progress', $prog); }
$qz = get_user_meta($uid, '_sfwd-quizzes', true);
if (is_array($qz)) { update_user_meta($uid, '_sfwd-quizzes', array_values(array_filter($qz, fn($q) => (int) ($q['quiz'] ?? 0) !== $quiz))); }
delete_user_meta($uid, "course_completed_{$course}");
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)", $uid, "completed_{$course}_%", 'hcp_mca_v2_%'));
$pro = (int) get_post_meta($quiz, 'quiz_pro_id', true);
if ($pro) {
	$refs = $wpdb->get_col($wpdb->prepare("SELECT statistic_ref_id FROM {$wpdb->prefix}learndash_pro_quiz_statistic_ref WHERE user_id = %d AND quiz_id = %d", $uid, $pro));
	foreach ($refs as $rid) { $wpdb->delete($wpdb->prefix . 'learndash_pro_quiz_statistic', ['statistic_ref_id' => $rid], ['%d']); $wpdb->delete($wpdb->prefix . 'learndash_pro_quiz_statistic_ref', ['statistic_ref_id' => $rid], ['%d']); }
}
ld_update_course_access($uid, $course, false);
wp_cache_flush();
echo json_encode(['user' => $u->user_login, 'uid' => $uid, 'course' => $course, 'lesson' => $lesson, 'quiz' => $quiz, 'removed' => count($ids)]) . "\n";
