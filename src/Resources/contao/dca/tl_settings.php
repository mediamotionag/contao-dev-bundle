<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('memo_dev_legend', 'tl_settings', PaletteManipulator::POSITION_AFTER)
    ->addField('local_domains', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('dev_domains', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('content_freeze', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');


$GLOBALS['TL_DCA']['tl_settings']['fields']['dev_domains'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['dev_domains'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('tl_class' => 'clr long', 'mandatory' => false),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['local_domains'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['local_domains'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('tl_class' => 'clr long', 'mandatory' => false),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['content_freeze'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['content_freeze'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => array('tl_class' => 'clr w50', 'mandatory' => false),
);
