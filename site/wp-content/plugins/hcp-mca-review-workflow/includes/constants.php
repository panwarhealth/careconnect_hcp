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

// Formidable form IDs.
const HCP_MCA_AUDIT_FORM_ID = 161;   // "Retrospective analysis Form" — the 5-step, 397-field clinical audit
const HCP_MCA_EVAL_FORM_ID  = 209;   // "Activity evaluation" — post-audit CPD feedback survey

// Admin notification recipient.
const HCP_MCA_ADMIN_EMAIL = 'education@panwarhealth.com.au';
