<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

/**
 * Content Freeze - Backend
 */
$GLOBALS['TL_LANG']['MSC']['content_freeze_title'] = 'Content Freeze Active';
$GLOBALS['TL_LANG']['MSC']['content_freeze_message'] = 'Only administrators can log in during the content freeze period.';

/**
 * Content Freeze - Frontend
 */
$GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_title'] = 'Maintenance';
$GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_message'] = 'Maintenance is in progress. Login is currently not possible.';

/**
 * Version switch
 */
$GLOBALS['TL_LANG']['MSC']['memo_vs_current'] = 'Current: %s.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_no_repository'] = 'The project directory is not a git checkout.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_blocked'] = 'Currently not possible:';
$GLOBALS['TL_LANG']['MSC']['memo_vs_fetch'] = 'Fetch tags';
$GLOBALS['TL_LANG']['MSC']['memo_vs_fetched'] = 'The tags have been fetched.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_no_run'] = 'No run yet.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step'] = 'Step';
$GLOBALS['TL_LANG']['MSC']['memo_vs_backup'] = 'Backup';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_switch'] = 'Switch to %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_rollback'] = 'Rollback to %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_restore'] = 'Restore of %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_queued'] = 'Starting';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_running'] = 'Running';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_success'] = 'Successful';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed_unchanged'] = 'Failed, nothing changed';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed_reverted'] = 'Failed, code reverted (database not)';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed'] = 'Failed, unknown state';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_aborted'] = 'Aborted';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_title'] = 'Version switch';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_switch'] = 'Switch';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_log'] = 'Log';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_rollback'] = 'Rollback to %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_restore'] = 'Restore database';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_close'] = 'Close';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_busy'] = 'No response from the server – composer install is probably still running. Retrying in a few seconds …';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_run'] = 'Run';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_no_tag'] = 'Select a tag first.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_switch'] = 'Switch to %s? contao:migrate creates a database backup before migrating.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_rollback'] = 'Reset the code to %s? The database stays unchanged.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_restore'] = 'Restore the database from %s? All changes since this backup are lost.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_elapsed'] = 'Duration';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_details'] = 'Log';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_preflight'] = 'Check';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_checkout'] = 'Check out code';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_composer'] = 'Composer install';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_migrate'] = 'Backup & migration';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_cache'] = 'Cache & assets';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_restore'] = 'Restore backup';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_revert'] = 'Revert code';
$GLOBALS['TL_LANG']['MSC']['memo_vs_current_option'] = '(current)';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_already_current'] = '%s is already active. Select another tag.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_session'] = 'The login has expired. The job keeps running in the background.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_relogin'] = 'Log in again';
