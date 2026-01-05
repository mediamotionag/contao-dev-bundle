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
        if (str_contains($buffer, 'id="content-freeze-banner"')) {
            return $buffer;
        }

        // Load language file and get translations
        System::loadLanguageFile('default');
        $title = $GLOBALS['TL_LANG']['MSC']['content_freeze_title'] ?? 'Content Freeze Active';
        $message = $GLOBALS['TL_LANG']['MSC']['content_freeze_message'] ?? 'Only administrators can log in during the content freeze period.';

        $banner = '
            <div id="content-freeze-banner" style="
                background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
                color: white;
                padding: 20px 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            ">
                <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                    <span style="font-size: 32px;">🔒</span>
                    <div>
                        <strong style="font-size: 18px; display: block; margin-bottom: 5px;">' . htmlspecialchars($title) . '</strong>
                        <span style="opacity: 0.9;">' . htmlspecialchars($message) . '</span>
                    </div>
                </div>
            </div>';

        // Insert banner right after the opening body tag
        $buffer = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $buffer);

        // Add background-position-y style to body
        // If body has a style attribute, append to it; otherwise add the style attribute
        if (preg_match('/<body[^>]*style=/i', $buffer)) {
            $buffer = preg_replace('/(<body[^>]*style=["\'])([^"\']*)/i', '$1background-position-y: 9em; $2', $buffer);
        } else {
            $buffer = preg_replace('/(<body)([^>]*>)/i', '$1 style="background-position-y: 9em;"$2', $buffer);
        }

        return $buffer;
    }
}
