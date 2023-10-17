<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_settings']['memo_dev_legend'] = 'Entwickler Einstellungen';

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_settings']['fields']['dev_domains'] = array('Stage Domänen', 'Fügen Sie kommaseparierte Liste von Domänen (-Bestandteilen), die als Stage-Umgebung erkannt werden sollten. Beispiel: stage.,dev.,new., etc. Diese werden einzeln in der URL gesucht. Falls einer oder mehrere dieser Bestandteile gefunden wird, wird die Seite z.B. mit einem noindex/nofollow ausgespielt und im Backend wird ein Label "Stage" angezeigt.');
$GLOBALS['TL_LANG']['tl_settings']['fields']['local_domains'] = array('Lokale Domänen', 'Fügen Sie kommaseparierte Liste von Domänen (-Bestandteilen), die als Lokale-Umgebung erkannt werden sollten. Beispiel: .local,.memo, etc. Diese werden einzeln in der URL gesucht. Falls einer oder mehrere dieser Bestandteile gefunden wird, wird die Seite z.B. mit einem noindex/nofollow ausgespielt und im Backend wird ein Label "Local" angezeigt.');
$GLOBALS['TL_LANG']['tl_settings']['fields']['content_freeze'] = array('Ist ein Content-Freeze verhängt?', 'Falls ein Content-Freeze verhängt wurde, wird ein Label "Content-Freeze" beim Login & in der Backend-Toolbar angezeigt.');
$GLOBALS['TL_LANG']['tl_settings']['fields']['backend_title'] = array('Backend Titel', 'Falls ein Backend Titel definiert wurde, wird dieser neben dem Contao-Logo angezeigt.');
