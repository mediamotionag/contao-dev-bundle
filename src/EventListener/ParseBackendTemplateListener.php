<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\System;
use Memo\DevBundle\Service\DomainMatcher;

#[AsHook('parseBackendTemplate')]
class ParseBackendTemplateListener
{
    public function __invoke(string $buffer, string $template): string
    {
        if (in_array($template, ['be_main', 'be_login'])) {

            // Backend Title
            $strBackendTitle = Config::get('backend_title');
            if($strBackendTitle != ''){
                $buffer = preg_replace('/app-title(.*?)<\/span>/', 'app-title">Contao | '.$strBackendTitle.'</span>', $buffer);
            }

            // Default Badge
            $strBadge = "Live";
            $strClass = "badge-title--live";

            // Detect Local and set Badge
            $bolLocalDomain = DomainMatcher::checkDomain('local_domains');
            if($bolLocalDomain === true)
            {
                $strBadge = "Local";
                $strClass = "badge-title--local";
            }

            // Detect Stage and set Badge
            $bolStageDomain = DomainMatcher::checkDomain('dev_domains');
            if($bolStageDomain === true)
            {
                $strBadge = "Stage";
                $strClass = "badge-title--stage";
            }

            // Detect Content Freeze and set Badge
            $bolContentFreeze = Config::get('content_freeze');
            if($bolContentFreeze == true){
                $strBadge .= " + Content Freeze";
                $strClass .= " badge-title--freeze";

                // Inject content freeze banner on login page
                if ($template === 'be_login') {
                    $buffer = $this->injectContentFreezeBanner($buffer);
                }
            }

            if($strClass != ''){
                $buffer = preg_replace('/badge-title(.*?)<\/span>/', 'badge-title '.$strClass.'">'.$strBadge.'</span>', $buffer);
            }

        }

        return $buffer;
    }

    private function injectContentFreezeBanner(string $buffer): string
    {
        // Prevent duplicate banner injection
        if (strpos($buffer, 'id="content-freeze-banner"') !== false) {
            return $buffer;
        }

        // Load language file and get translations
        System::loadLanguageFile('default');
        $title = isset($GLOBALS['TL_LANG']['MSC']['content_freeze_title']) ? $GLOBALS['TL_LANG']['MSC']['content_freeze_title'] : 'Content Freeze Active';
        $message = isset($GLOBALS['TL_LANG']['MSC']['content_freeze_message']) ? $GLOBALS['TL_LANG']['MSC']['content_freeze_message'] : 'Only administrators can log in during the content freeze period.';

        $banner = '
            <div id="content-freeze-banner" style="
                background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
                color: white;
                padding: 20px 30px;
                text-align: center;
                font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                position: relative;
                z-index: 9999;
            ">
                <div style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <span style="font-size: 32px;">🔒</span>
                    <div style="text-align: left;">
                        <strong style="font-size: 18px; display: block; margin-bottom: 5px;">' . htmlspecialchars($title) . '</strong>
                        <span style="font-size: 14px; opacity: 0.9;">' . htmlspecialchars($message) . '</span>
                    </div>
                </div>
            </div>';

        // Insert banner right after the opening body tag
        $buffer = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $buffer);

        // Add background-position-y style to body
        if (preg_match('/<body[^>]*style=/i', $buffer)) {
            $buffer = preg_replace('/(<body[^>]*style=["\'])([^"\']*)/i', '$1background-position-y: 9em; $2', $buffer);
        } else {
            $buffer = preg_replace('/(<body)([^>]*>)/i', '$1 style="background-position-y: 9em;"$2', $buffer);
        }

        return $buffer;
    }
}
