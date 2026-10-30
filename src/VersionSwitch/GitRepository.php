<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\VersionSwitch;

use Symfony\Component\Process\Process;

class GitRepository
{
    public const TAG_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/';

    public function __construct(
        private readonly string $projectDir,
        private readonly BinaryResolver $binaries,
    ) {
    }

    public function isRepository(): bool
    {
        return file_exists($this->projectDir . '/.git');
    }

    public function fetchTags(): void
    {
        // --force: the release CI re-creates tags as annotated tags, which would otherwise be rejected as clobbering
        $this->git(['fetch', '--tags', '--prune', '--prune-tags', '--force', 'origin'], 120);
    }

    /**
     * @return list<array{name: string, commit: string, date: ?\DateTimeImmutable, subject: string}>
     */
    public function listTags(): array
    {
        $output = $this->git([
            'for-each-ref',
            '--sort=-v:refname',
            '--format=%(refname:short)%09%(objectname)%09%(*objectname)%09%(creatordate:iso-strict)%09%(subject)',
            'refs/tags',
        ]);

        $tags = [];

        foreach (array_filter(explode("\n", $output)) as $line) {
            [$name, $object, $peeled, $date, $subject] = array_pad(explode("\t", $line, 5), 5, '');

            $tags[] = [
                'name' => $name,
                'commit' => '' !== $peeled ? $peeled : $object,
                'date' => '' !== $date ? new \DateTimeImmutable($date) : null,
                'subject' => $subject,
            ];
        }

        return $tags;
    }

    /**
     * @return array{commit: string, branch: ?string, tag: ?string}
     */
    public function getCurrentRef(): array
    {
        return [
            'commit' => trim($this->git(['rev-parse', 'HEAD'])),
            'branch' => $this->gitOrNull(['symbolic-ref', '--short', '-q', 'HEAD']),
            'tag' => $this->gitOrNull(['describe', '--tags', '--exact-match', 'HEAD']),
        ];
    }

    /**
     * The ref to return to after a failed switch: the branch keeps HEAD attached, so it wins over the tag.
     */
    public function getRevertRef(): string
    {
        $ref = $this->getCurrentRef();

        if (null !== $ref['branch']) {
            return $ref['branch'];
        }

        if (null !== $ref['tag']) {
            return 'refs/tags/' . $ref['tag'];
        }

        return $ref['commit'];
    }

    /**
     * @return list<string>
     */
    public function getModifiedFiles(): array
    {
        return array_values(array_filter(explode("\n", $this->git(['status', '--porcelain', '--untracked-files=no']))));
    }

    public function tagExists(string $tag): bool
    {
        if (!preg_match(self::TAG_PATTERN, $tag)) {
            return false;
        }

        return null !== $this->gitOrNull(['rev-parse', '-q', '--verify', 'refs/tags/' . $tag . '^{commit}']);
    }

    public function refExists(string $ref): bool
    {
        return null !== $this->gitOrNull(['rev-parse', '-q', '--verify', $ref . '^{commit}']);
    }

    public function checkout(string $ref): void
    {
        $this->git(['-c', 'advice.detachedHead=false', 'checkout', '-q', $ref], 120);
    }

    /**
     * @param list<string> $args
     */
    private function git(array $args, int $timeout = 60): string
    {
        $process = new Process(['git', ...$args], $this->projectDir, $this->binaries->getEnv(), null, $timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('git %s failed: %s', $args[0], trim($process->getErrorOutput() ?: $process->getOutput())));
        }

        return $process->getOutput();
    }

    /**
     * @param list<string> $args
     */
    private function gitOrNull(array $args): ?string
    {
        $process = new Process(['git', ...$args], $this->projectDir, $this->binaries->getEnv(), null, 60);
        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && '' !== $output ? $output : null;
    }
}
