<?php
/**
 * Constants for the MCA review workflow.
 */

defined( 'ABSPATH' ) || exit;

// LearnDash post IDs.
const HCP_MCA_COURSE_ID           = 111793;   // sfwd-courses   — "Mini Clinical Audit"
const HCP_MCA_LESSON_ID           = 112353;   // sfwd-lessons   — "Complete Mini Clinical Audit" (embeds audit form)
const HCP_MCA_QUIZ_ID             = 116865;   // sfwd-quiz      — "Activity evaluation" (embeds eval form)
const HCP_MCA_CERT_ID             = 96129;    // sfwd-certificates — "Clinical Audit Module" (auto-issued on course complete)
const HCP_MCA_LEARNING_COURSE_ID  = 95553;    // sfwd-courses   — "Online Learning Module" (prerequisite, auto-completes)
const HCP_MCA_LEARNING_ACTIVITY_ID = '1460034'; // RACGP activity ID of the Online Learning Module

// Formidable form IDs.
const HCP_MCA_CONTACT_FORM_ID     = 113;   // "Course Contact Form" — name, email and enquiry from HCPs
const HCP_MCA_PRE_SURVEY_FORM_ID  = 81;    // "Pre-learning survey"
const HCP_MCA_POST_SURVEY_FORM_ID = 97;    // "Post-learning survey"
const HCP_MCA_AUDIT_FORM_ID       = 161;   // "Retrospective analysis Form" — the 5-step, 397-field clinical audit
const HCP_MCA_REPEATER_FORM_ID    = 177;   // "Repeater" — per-patient records, child form of 161
const HCP_MCA_EVAL_FORM_ID        = 209;   // "Activity evaluation" — post-audit CPD feedback survey
const HCP_MCA_AUDIT_BANNER_FIELD_ID = 12465; // HTML field above the submit button on form 161 (submission-state banner)

// 2026 audit variant. Created by migration, so identified by slug / key and
// resolved at runtime in variants.php.
const HCP_MCA_V2_COURSE_SLUG            = 'clinical-audit-anal-fissure-management';
const HCP_MCA_V2_LESSON_SLUG            = 'complete-clinical-audit';
const HCP_MCA_V2_QUIZ_SLUG              = 'clinical-audit-activity-evaluation';
const HCP_MCA_V2_CERT_SLUG              = 'clinical-audit-2026';
const HCP_MCA_V2_AUDIT_FORM_KEY         = 'clinical-audit-v2';
const HCP_MCA_V2_EVAL_FORM_KEY          = 'activity-evaluation-v2';
const HCP_MCA_V2_FIELD_KEY_PREFIX       = 'v2-';   // duplicated fields keep their legacy key behind this prefix
const HCP_MCA_V2_AUDIT_BANNER_FIELD_KEY = HCP_MCA_V2_FIELD_KEY_PREFIX . '3ng4q';

// Chooser page shown to users with legacy progress.
const HCP_MCA_CHOOSER_PAGE_SLUG = 'clinical-audit';

// Admin notification recipient.
const HCP_MCA_ADMIN_EMAIL = 'education@panwarhealth.com.au';
