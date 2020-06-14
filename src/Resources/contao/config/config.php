<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

/**
 * HOOKS
 */

//Generelle Hooks
$GLOBALS['TL_HOOKS']['generatePage'][] = array('Memo\DevBundle\Service\HookListener', 'setDevSettings');