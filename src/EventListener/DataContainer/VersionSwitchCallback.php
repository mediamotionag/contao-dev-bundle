<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener\DataContainer;

use Contao\StringUtil;
use Memo\DevBundle\Controller\VersionSwitchController;
use Memo\DevBundle\VersionSwitch\GitRepository;
use Memo\DevBundle\VersionSwitch\RunFormatter;
use Memo\DevBundle\VersionSwitch\StatusStore;
use Memo\DevBundle\VersionSwitch\SwitchLauncher;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * memo_version_tag is only a picker for the "Wechseln" button: it loads the checked-out tag and save returns '',
 * so saving the settings never switches and nothing is written to localconfig.php.
 */
class VersionSwitchCallback implements ResetInterface
{
    private const MODAL_LABELS = [
        'title', 'switch', 'log', 'rollback', 'restore', 'close', 'busy', 'run', 'elapsed', 'details',
        'confirm_switch', 'confirm_rollback', 'confirm_restore', 'no_tag', 'already_current', 'session', 'relogin',
        'restore_no_migration', 'restore_restored', 'restore_missing',
    ];

    /** One settings page asks for the ref four times, each a set of git processes */
    private ?array $currentRef = null;

    public function __construct(
        private readonly GitRepository $git,
        private readonly SwitchLauncher $launcher,
        private readonly StatusStore $store,
        private readonly RunFormatter $formatter,
        private readonly TranslatorInterface $translator,
        private readonly RouterInterface $router,
    ) {
    }

    public function reset(): void
    {
        $this->currentRef = null;
    }

    public function onLoad(): void
    {
        $fields = &$GLOBALS['TL_DCA']['tl_settings']['fields'];

        // The label references $GLOBALS['TL_LANG']; copy before unset so the formatted text does not write back into it
        $label = $fields['memo_version_tag']['label'] ?? ['', '%s'];
        unset($fields['memo_version_tag']['label']);

        $fields['memo_version_tag']['label'] = [
            $label[0],
            sprintf($label[1], $this->describeCurrent() . ' ' . $this->formatter->summarize($this->store->getLatestRun())),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getTagOptions(): array
    {
        try {
            $commit = $this->getCurrentRef()['commit'];
            $tags = $this->git->listTags();
        } catch (\Throwable) {
            return [];
        }

        $options = [];

        $current = $this->translator->trans('MSC.memo_vs_current_option', [], 'contao_default');

        // All tags, newest first: the chosen select has a search box, so a long list stays usable
        foreach ($tags as $tag) {
            $isCurrent = $tag['commit'] === $commit;

            $options[$tag['name']] = implode(' – ', array_filter([
                $tag['name'] . ($isCurrent ? ' ' . $current : ''),
                $tag['date']?->format('d.m.Y'),
                mb_strimwidth($tag['subject'], 0, 60, '…'),
            ]));
        }

        return $options;
    }

    /**
     * Preselects the checked-out tag; saveEmpty() keeps it out of localconfig.php.
     */
    public function loadCurrentTag(): string
    {
        try {
            return $this->getCurrentRef()['tag'] ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    public function saveEmpty(): string
    {
        return '';
    }

    /**
     * "Wechseln" sits next to the select, "Tags abrufen" and "Protokoll" in a row below it. "Tags abrufen" posts the
     * form to its own route (formaction); "Wechseln" and "Protokoll" are handled by backend.js.
     */
    public function getButtons(): string
    {
        $labels = [];

        foreach (self::MODAL_LABELS as $key) {
            $labels[$key] = $this->translator->trans('MSC.memo_vs_modal_' . $key, [], 'contao_default');
        }

        $labels['step'] = $this->translator->trans('MSC.memo_vs_step', [], 'contao_default');
        $labels['backup'] = $this->translator->trans('MSC.memo_vs_backup', [], 'contao_default');

        foreach (['preflight', 'checkout', 'composer', 'migrate', 'cache', 'restore', 'revert'] as $step) {
            $labels['steps'][$step] = $this->translator->trans('MSC.memo_vs_step_' . $step, [], 'contao_default');
        }

        $urls = [
            'status' => $this->router->generate(VersionSwitchController::ROUTE_STATUS),
            'switch' => $this->router->generate(VersionSwitchController::ROUTE_SWITCH),
            'rollback' => $this->router->generate(VersionSwitchController::ROUTE_ROLLBACK),
            'restore' => $this->router->generate(VersionSwitchController::ROUTE_RESTORE),
        ];

        return sprintf(
            ' <button type="button" class="tl_submit" data-memo-version-action="switch">%s</button>'
            . '<div class="memo-version-tag__tools">'
            . '<button type="submit" name="memo_version_fetch" value="1" class="tl_submit" formaction="%s" formnovalidate>%s</button>'
            . ' <button type="button" class="tl_submit" data-memo-version-action="log">%s</button>'
            . '</div>'
            . '<span hidden data-memo-version-switch data-current="%s" data-urls="%s" data-labels="%s"></span>',
            StringUtil::specialchars($labels['switch']),
            StringUtil::specialchars($this->router->generate(VersionSwitchController::ROUTE_FETCH)),
            StringUtil::specialchars($this->translator->trans('MSC.memo_vs_fetch', [], 'contao_default')),
            StringUtil::specialchars($labels['log']),
            StringUtil::specialchars($this->loadCurrentTag()),
            StringUtil::specialchars(json_encode($urls, JSON_UNESCAPED_SLASHES)),
            StringUtil::specialchars(json_encode($labels, JSON_UNESCAPED_UNICODE))
        );
    }

    private function describeCurrent(): string
    {
        try {
            if (!$this->git->isRepository()) {
                return $this->translator->trans('MSC.memo_vs_no_repository', [], 'contao_default');
            }

            $ref = $this->getCurrentRef();
            $text = sprintf(
                $this->translator->trans('MSC.memo_vs_current', [], 'contao_default'),
                implode(', ', array_filter([$ref['tag'], $ref['branch'], substr($ref['commit'], 0, 10)]))
            );

            if ([] !== ($blockers = $this->launcher->getBlockers())) {
                $text .= ' ' . $this->translator->trans('MSC.memo_vs_blocked', [], 'contao_default') . ' ' . implode(' ', $blockers);
            }

            return $text;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return array{commit: string, branch: ?string, tag: ?string}
     */
    private function getCurrentRef(): array
    {
        return $this->currentRef ??= $this->git->getCurrentRef();
    }
}
