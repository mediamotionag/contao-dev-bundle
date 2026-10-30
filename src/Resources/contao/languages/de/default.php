<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

/**
 * Content Freeze - Backend
 */
$GLOBALS['TL_LANG']['MSC']['content_freeze_title'] = 'Content Freeze aktiv';
$GLOBALS['TL_LANG']['MSC']['content_freeze_message'] = 'Nur Administratoren können sich während des Content Freeze einloggen.';

/**
 * Content Freeze - Frontend
 */
$GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_title'] = 'Wartungsarbeiten';
$GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_message'] = 'Es werden Wartungsarbeiten durchgeführt. Aktuell ist keine Anmeldung möglich.';

/**
 * Version switch
 */
$GLOBALS['TL_LANG']['MSC']['memo_vs_current'] = 'Aktuell: %s.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_no_repository'] = 'Das Projektverzeichnis ist kein git-Checkout.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_blocked'] = 'Derzeit nicht möglich:';
$GLOBALS['TL_LANG']['MSC']['memo_vs_fetch'] = 'Tags abrufen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_fetched'] = 'Die Tags wurden abgerufen.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_no_run'] = 'Noch kein Lauf.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step'] = 'Schritt';
$GLOBALS['TL_LANG']['MSC']['memo_vs_backup'] = 'Backup';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_switch'] = 'Wechsel auf %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_rollback'] = 'Rollback auf %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_action_restore'] = 'Wiederherstellung von %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_queued'] = 'Wird gestartet';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_running'] = 'Läuft';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_success'] = 'Erfolgreich';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed_unchanged'] = 'Fehlgeschlagen, nichts geändert';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed_reverted'] = 'Fehlgeschlagen, Code zurückgesetzt (Datenbank nicht)';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_failed'] = 'Fehlgeschlagen, Zustand unklar';
$GLOBALS['TL_LANG']['MSC']['memo_vs_state_aborted'] = 'Abgebrochen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_title'] = 'Versionswechsel';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_switch'] = 'Wechseln';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_log'] = 'Protokoll';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_rollback'] = 'Rollback auf %s';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_restore'] = 'Datenbank wiederherstellen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_close'] = 'Schliessen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_busy'] = 'Keine Antwort vom Server – composer install läuft vermutlich noch. Neuer Versuch in wenigen Sekunden …';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_run'] = 'Lauf';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_no_tag'] = 'Wählen Sie zuerst einen Tag aus.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_switch'] = 'Auf %s wechseln? Vor der Migration erstellt contao:migrate ein Datenbank-Backup.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_rollback'] = 'Den Code auf %s zurücksetzen? Die Datenbank bleibt unverändert.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_confirm_restore'] = 'Die Datenbank aus %s wiederherstellen? Alle Änderungen seit diesem Backup gehen verloren.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_elapsed'] = 'Laufzeit';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_details'] = 'Protokoll';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_preflight'] = 'Prüfen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_checkout'] = 'Code auschecken';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_composer'] = 'Composer install';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_migrate'] = 'Backup & Migration';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_cache'] = 'Cache & Assets';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_restore'] = 'Backup wiederherstellen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_step_revert'] = 'Code zurücksetzen';
$GLOBALS['TL_LANG']['MSC']['memo_vs_current_option'] = '(aktuell)';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_already_current'] = '%s ist bereits aktiv. Wählen Sie einen anderen Tag.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_session'] = 'Die Anmeldung ist abgelaufen. Der Job läuft im Hintergrund weiter.';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_relogin'] = 'Neu anmelden';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_restore_no_migration'] = 'Keine Datenbankänderung';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_restore_restored'] = 'Backup bereits wiederhergestellt';
$GLOBALS['TL_LANG']['MSC']['memo_vs_modal_restore_missing'] = 'Backup nicht mehr vorhanden';
