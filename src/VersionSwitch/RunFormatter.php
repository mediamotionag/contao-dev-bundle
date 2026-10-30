<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\VersionSwitch;

use Symfony\Contracts\Translation\TranslatorInterface;

class RunFormatter
{
    // Seconds per step until successful runs with step times exist to average over
    private const DEFAULT_DURATIONS = [
        'preflight' => 2, 'checkout' => 2, 'composer' => 60, 'migrate' => 15, 'cache' => 15, 'restore' => 30, 'revert' => 60,
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly StatusStore $store,
    ) {
    }

    /**
     * One line for the run selector: "30.10.2026 14:57 – Wechsel auf v4.3.2 – Erfolgreich".
     */
    public function label(array $run): string
    {
        return implode(' – ', [
            date('d.m.Y H:i', (int) strtotime((string) ($run['startedAt'] ?? 'now'))),
            sprintf($this->trans('MSC.memo_vs_action_' . ($run['action'] ?? 'switch')), self::displayRef($run['tag'] ?? '')),
            $this->trans('MSC.memo_vs_state_' . ($run['state'] ?? '')),
        ]);
    }

    public function summarize(?array $run): string
    {
        if (null === $run) {
            return $this->trans('MSC.memo_vs_no_run');
        }

        return implode(' – ', array_filter([$this->label($run), $this->details($run)]));
    }

    /**
     * Step, backup and message of a run, without the label the run selector already shows.
     */
    public function details(array $run): string
    {
        $parts = [];

        if ($run['running'] ?? false) {
            $parts[] = $this->trans('MSC.memo_vs_step') . ': ' . ($run['step'] ?? '…');
        }

        if (!empty($run['backup']) && 'restore' !== ($run['action'] ?? 'switch')) {
            $parts[] = $this->trans('MSC.memo_vs_backup') . ': ' . $run['backup'];
        }

        if (!empty($run['message'])) {
            $parts[] = $run['message'];
        }

        return implode(' – ', $parts);
    }

    /**
     * The steps of a run with their state (done, active, failed, pending, skipped) and the overall progress in percent.
     *
     * Labels are added by backend.js, so the session-free poll route needs no translator.
     *
     * @return array{steps: list<array{key: string, state: string}>, progress: float}
     */
    public function steps(array $run): array
    {
        $keys = 'restore' === ($run['action'] ?? 'switch') ? ['restore', 'cache'] : ['preflight', 'checkout', 'composer', 'migrate', 'cache'];
        $state = $run['state'] ?? '';
        $current = $run['failedStep'] ?? $run['step'] ?? null;
        $position = array_search($current, $keys, true);
        $steps = [];

        foreach ($keys as $i => $key) {
            $stepState = match (true) {
                StatusStore::STATE_SUCCESS === $state => 'done',
                false === $position => 'pending',
                $i < $position => 'done',
                $i > $position => ($run['running'] ?? false) ? 'pending' : 'skipped',
                ($run['running'] ?? false) && !isset($run['failedStep']) => 'active',
                default => 'failed',
            };

            $steps[] = ['key' => $key, 'state' => $stepState];
        }

        if (isset($run['failedStep']) && 'restore' !== ($run['action'] ?? 'switch')) {
            $steps[] = [
                'key' => 'revert',
                'state' => match ($state) {
                    StatusStore::STATE_FAILED_REVERTED => 'done',
                    StatusStore::STATE_RUNNING => 'active',
                    default => 'failed',
                },
            ];
        }

        return [
            'steps' => $steps,
            'progress' => StatusStore::STATE_SUCCESS === $state ? 100 : $this->progress($run, $steps),
        ];
    }

    /**
     * Steps weigh by their expected duration; the active one advances with its elapsed time and
     * slows down near its end instead of overshooting, so the bar moves every second.
     */
    private function progress(array $run, array $steps): float
    {
        $expected = $this->store->getAverageStepDurations($run['action'] ?? 'switch') + self::DEFAULT_DURATIONS;
        $total = 0.0;
        $reached = 0.0;

        foreach ($steps as $step) {
            $weight = (float) ($expected[$step['key']] ?? 10);
            $total += $weight;

            if ('done' === $step['state']) {
                $reached += $weight;
            } elseif ('active' === $step['state'] && isset($run['stepTimes'][$step['key']])) {
                $ratio = (microtime(true) - $run['stepTimes'][$step['key']]) / $weight;
                $fraction = $ratio < .9 ? $ratio : .9 + .09 * (1 - exp(-($ratio - .9) * 3));
                $reached += $weight * max(0, $fraction);
            }
        }

        return $total > 0 ? round(min(99.0, $reached / $total * 100), 1) : 0.0;
    }

    public static function displayRef(string $ref): string
    {
        return preg_replace('#^refs/tags/#', '', $ref);
    }

    private function trans(string $id): string
    {
        return $this->translator->trans($id, [], 'contao_default');
    }
}
