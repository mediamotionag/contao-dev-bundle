<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\Message;
use Memo\DevBundle\VersionSwitch\GitRepository;
use Memo\DevBundle\VersionSwitch\RunFormatter;
use Memo\DevBundle\VersionSwitch\StatusStore;
use Memo\DevBundle\VersionSwitch\SwitchLauncher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Endpoints behind the version field and modal in System → Einstellungen (tl_settings).
 */
#[AsController]
#[Route('%contao.backend.route_prefix%/memo-dev', defaults: ['_scope' => 'backend', '_token_check' => true])]
class VersionSwitchController extends AbstractController
{
    public const ROUTE_STATUS = 'memo_dev_version_status';
    public const ROUTE_FETCH = 'memo_dev_version_fetch';
    public const ROUTE_SWITCH = 'memo_dev_version_switch';
    public const ROUTE_ROLLBACK = 'memo_dev_version_rollback';
    public const ROUTE_RESTORE = 'memo_dev_version_restore';

    public function __construct(
        private readonly GitRepository $git,
        private readonly StatusStore $store,
        private readonly SwitchLauncher $launcher,
        private readonly RunFormatter $formatter,
        private readonly TranslatorInterface $translator,
        private readonly VersionPollController $poll,
    ) {
    }

    /**
     * The selected run (default: latest) with its log tail, the run list and which actions are possible.
     * Called when the dialog opens and once a run ends; live progress comes from VersionPollController.
     */
    #[Route('/version-status', name: self::ROUTE_STATUS, methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $runId = (string) $request->query->get('run', '');
        $run = '' !== $runId ? $this->store->getRun($runId) : $this->store->getLatestRun();

        if (null !== $run) {
            $run += $this->formatter->steps($run);
            $run['pollUrl'] = $this->generateUrl(VersionPollController::ROUTE, [
                'runId' => $run['runId'],
                'token' => $this->poll->createToken($run['runId']),
            ]);
        }

        $rollback = $this->store->getRollbackTarget();
        $latest = $this->store->getRuns()[0] ?? null;

        $response = new JsonResponse([
            'run' => $run,
            'runs' => array_map(fn (array $item): array => [
                'runId' => $item['runId'],
                'label' => $this->formatter->label($item),
            ], $this->store->getRuns()),
            'running' => (bool) ($latest['running'] ?? false),
            'rollbackRef' => isset($rollback['previousRef']) ? RunFormatter::displayRef($rollback['previousRef']) : null,
            'restoreBackup' => $this->store->getRestoreBackup(),
        ]);

        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * Posted by the "Tags abrufen" button via formaction, so the settings form itself is not saved.
     */
    #[Route('/version-fetch', name: self::ROUTE_FETCH, methods: ['POST'])]
    public function fetch(): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->initializeContaoFramework();

        try {
            $this->git->fetchTags();
            Message::addConfirmation($this->translator->trans('MSC.memo_vs_fetched', [], 'contao_default'));
        } catch (\Throwable $e) {
            Message::addError($e->getMessage());
        }

        return $this->redirectToRoute('contao_backend', ['do' => 'settings'], 303);
    }

    #[Route('/version-switch', name: self::ROUTE_SWITCH, methods: ['POST'])]
    public function switch(Request $request): JsonResponse
    {
        return $this->action(fn (): array => ['runId' => $this->launcher->launchSwitch((string) $request->request->get('tag'))]);
    }

    #[Route('/version-rollback', name: self::ROUTE_ROLLBACK, methods: ['POST'])]
    public function rollback(): JsonResponse
    {
        return $this->action(fn (): array => ['runId' => $this->launcher->launchRollback()]);
    }

    #[Route('/version-restore', name: self::ROUTE_RESTORE, methods: ['POST'])]
    public function restore(): JsonResponse
    {
        return $this->action(fn (): array => ['runId' => $this->launcher->launchRestore()]);
    }


    private function action(\Closure $action): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            return new JsonResponse($action());
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }
}
