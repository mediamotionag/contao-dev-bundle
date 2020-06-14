<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
	->addLegend('memo_dev_legend', 'tl_settings', PaletteManipulator::POSITION_AFTER)
	->addField('dev_domains', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_settings');
	
	
$GLOBALS['TL_DCA']['tl_settings']['fields']['dev_domains'] = array(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['fields']['dev_domains'],
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('tl_class'=>'clr long', 'mandatory'=>false),
);