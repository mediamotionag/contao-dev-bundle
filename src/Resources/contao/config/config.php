<?php

// Legacy Fallback - Detect if it is Contao < 5.0
if (VERSION !== null && version_compare(VERSION, '5.0', '<')) {

    $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = ['memo.dev.pagebackendtemplatelistener', '__invoke'];

}
