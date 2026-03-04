<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Service;

use Contao\Config;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainMatcher
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function checkDomain(string $strTyp = 'dev_domains'): bool
    {
        // Check if the typ is valid
        if ($strTyp !== 'dev_domains' && $strTyp !== 'local_domains') {
            return false;
        }

        // Get Config value
        $strDomains = Config::get($strTyp);

        if ($strDomains === null || $strDomains === '') {
            return false;
        }

        $arrDomains = explode(',', $strDomains);
        $strCurrentDomain = $this->getCurrentHost();

        if ($strCurrentDomain === '') {
            return false;
        }

        foreach ($arrDomains as $strDomain) {
            $strDomain = str_replace([' ', '*'], '', $strDomain);
            $strDomain = urlencode($strDomain);

            if ($strDomain !== '' && stristr($strCurrentDomain, $strDomain)) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            return $request->getHost();
        }

        return $_SERVER['HTTP_HOST'] ?? '';
    }
}
