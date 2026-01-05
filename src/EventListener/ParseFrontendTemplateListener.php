<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;

class ParseFrontendTemplateListener
{
    protected $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Hook: parseFrontendTemplate
     * Injects maintenance message into login form when content freeze is active
     */
    public function __invoke(string $strBuffer, string $strTemplate): string
    {
        // Only handle login form templates
        if (strpos($strTemplate, 'mod_login') !== 0) {
            return $strBuffer;
        }

        // Check if content freeze is active
        if (!$this->isContentFreezeActive()) {
            return $strBuffer;
        }

        // Don't add banner if already present
        if (strpos($strBuffer, 'content-freeze-frontend-banner') !== false) {
            return $strBuffer;
        }

        // Load translations
        System::loadLanguageFile('default');

        $title = $GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_title'] ?? 'Wartungsarbeiten';
        $message = $GLOBALS['TL_LANG']['MSC']['content_freeze_frontend_message'] ?? 'Es werden Wartungsarbeiten durchgeführt. Aktuell ist keine Anmeldung möglich.';

        $banner = <<<HTML
            <div id="content-freeze-frontend-banner" style="background-color: #f44336; color: white; padding: 15px; margin-bottom: 15px; border-radius: 4px; text-align: center;">
                <strong style="font-size: 1.1em;">{$title}</strong><br>
                <span>{$message}</span>
            </div>
        HTML;

        // Insert banner at the beginning of the module
        if (strpos($strBuffer, '<form') !== false) {
            $strBuffer = preg_replace('/(<form[^>]*>)/i', $banner . '$1', $strBuffer, 1);
        } else {
            // Fallback: prepend to buffer
            $strBuffer = $banner . $strBuffer;
        }

        return $strBuffer;
    }

    private function isContentFreezeActive(): bool
    {
        try {
            $this->framework->initialize();
            return (bool) Config::get('content_freeze');
        } catch (\Exception $e) {
            return false;
        }
    }
}
