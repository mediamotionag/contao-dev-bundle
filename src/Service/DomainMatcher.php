<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Service;

use Contao\Config;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainMatcher
{
    /** @var RequestStack */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $strTyp 'dev_domains' or 'local_domains'
     * @return bool
     */
    public function checkDomain($strTyp = 'dev_domains')
    {
        if ($strTyp !== 'dev_domains' && $strTyp !== 'local_domains') {
            return false;
        }

        $strDomains = Config::get($strTyp);

        if ($strDomains == '') {
            return false;
        }

        $arrDomains = explode(',', $strDomains);

        if (!is_array($arrDomains) || count($arrDomains) === 0) {
            return false;
        }

        $strCurrentDomain = $this->getCurrentDomain();

        if ($strCurrentDomain === '') {
            return false;
        }

        foreach ($arrDomains as $strDomain) {
            $strDomain = str_replace(array(' ', '*'), '', $strDomain);
            $strDomain = urlencode($strDomain);

            if ($strDomain !== '' && stristr($strCurrentDomain, $strDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    private function getCurrentDomain()
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            return $request->getHost();
        }

        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    }
}
