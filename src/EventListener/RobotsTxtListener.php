<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\CoreBundle\Event\RobotsTxtEvent;
use Memo\DevBundle\Service\DomainMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use webignition\RobotsTxt\Directive\Directive;
use webignition\RobotsTxt\Directive\UserAgentDirective;
use webignition\RobotsTxt\Record\Record;

/**
 * Modifies robots.txt to disallow all crawling on staging and local domains.
 */
#[AsEventListener(event: 'contao.robots_txt', priority: -100)]
class RobotsTxtListener
{
    private DomainMatcher $domainMatcher;

    public function __construct(DomainMatcher $domainMatcher)
    {
        $this->domainMatcher = $domainMatcher;
    }

    public function __invoke(RobotsTxtEvent $event): void
    {
        // Only block crawling on staging and local domains
        $isStageDomain = $this->domainMatcher->checkDomain('dev_domains');
        $isLocalDomain = $this->domainMatcher->checkDomain('local_domains');

        if (!$isStageDomain && !$isLocalDomain) {
            return;
        }

        $file = $event->getFile();

        // Clear existing directives from all records
        foreach ($file->getRecords() as $existingRecord) {
            $directiveList = $existingRecord->getDirectiveList();
            foreach ($directiveList->getDirectives() as $directive) {
                $directiveList->remove($directive);
            }
        }

        // Remove non-group directives (like Sitemap)
        $nonGroupDirectives = $file->getNonGroupDirectives();
        foreach ($nonGroupDirectives->getDirectives() as $directive) {
            $nonGroupDirectives->remove($directive);
        }

        // Create a new record that disallows everything
        $record = new Record();
        $record->getUserAgentDirectiveList()->add(new UserAgentDirective('*'));
        $record->getDirectiveList()->add(new Directive('Disallow', '/'));

        // Add our disallow-all record
        $file->addRecord($record);
    }
}
