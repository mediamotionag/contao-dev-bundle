<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_settings']['memo_dev_legend'] = 'Developer Settings';

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_settings']['fields']['dev_domains'] = array('Stage Domains', 'Add a comma-separated list of domain parts that should be recognized as a Stage environment. Example: stage.,dev.,new., etc. These are searched individually in the URL. If one or more of these parts are found, the page is served with noindex/nofollow and a "Stage" label is displayed in the backend.');
$GLOBALS['TL_LANG']['tl_settings']['fields']['local_domains'] = array('Local Domains', 'Add a comma-separated list of domain parts that should be recognized as a Local environment. Example: .local,.memo, etc. These are searched individually in the URL. If one or more of these parts are found, the page is served with noindex/nofollow and a "Local" label is displayed in the backend.');
$GLOBALS['TL_LANG']['tl_settings']['fields']['content_freeze'] = array('Is a Content Freeze in effect?', 'If a Content Freeze is in effect, a "Content Freeze" label is displayed at login & in the backend toolbar.');
