<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Controller;

use Memo\DevBundle\VersionSwitch\RunFormatter;
use Memo\DevBundle\VersionSwitch\StatusStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Live progress of one run, polled every second by the version dialog.
 *
 * Deliberately outside /contao and without _scope, so no firewall reads or writes the backend session:
 * composer and the job clear the cache during a switch, a backend request crashing in that moment
 * saves the session without the login, and the admin is logged out. Access is a per-run HMAC token
 * that only the logged-in status route hands out.
 */
#[AsController]
class VersionPollController
{
    public const ROUTE = 'memo_dev_version_poll';

    public function __construct(
        private readonly StatusStore $store,
        private readonly RunFormatter $formatter,
        private readonly string $secret,
    ) {
    }

    public function createToken(string $runId): string
    {
        return hash_hmac('sha256', 'memo-dev-version-poll|' . $runId, $this->secret);
    }

    #[Route('/_memo-dev/version-poll/{runId}', name: self::ROUTE, requirements: ['runId' => '\d{8}-\d{6}-[a-f0-9]{6}'], methods: ['GET'])]
    public function __invoke(Request $request, string $runId): JsonResponse
    {
        if (!hash_equals($this->createToken($runId), (string) $request->query->get('token')) || null === ($run = $this->store->getRunState($runId))) {
            throw new NotFoundHttpException();
        }

        $response = new JsonResponse([
            'run' => $run + $this->store->readLog($runId, max(0, $request->query->getInt('offset'))) + $this->formatter->steps($run),
        ]);

        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
