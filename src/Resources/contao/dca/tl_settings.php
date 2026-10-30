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
    ->addField('backend_title', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('content_freeze', 'memo_dev_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');

PaletteManipulator::create()
    ->addLegend('memo_version_legend', 'memo_dev_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('memo_version_tag', 'memo_version_legend', PaletteManipulator::POSITION_APPEND)
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

$GLOBALS['TL_DCA']['tl_settings']['fields']['backend_title'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['backend_title'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('tl_class' => 'w50', 'mandatory' => false),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['content_freeze'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['content_freeze'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => array('tl_class' => 'clr w50', 'mandatory' => false),
);

// Picker for the version switch buttons; VersionSwitchCallback keeps it out of localconfig.php
$GLOBALS['TL_DCA']['tl_settings']['fields']['memo_version_tag'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fields']['memo_version_tag'],
    'exclude' => true,
    'inputType' => 'select',
    'eval' => array('tl_class' => 'clr w50 memo-version-tag', 'includeBlankOption' => true, 'chosen' => true, 'doNotSaveEmpty' => true),
);
