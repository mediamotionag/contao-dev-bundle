<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\VersionSwitch;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class BinaryResolver
{
    public function __construct(
        private readonly string $phpBinary,
        private readonly string $composerBinary,
    ) {
    }

    public function getPhp(): string
    {
        if ('' !== $this->phpBinary) {
            return $this->phpBinary;
        }

        // Under PHP-FPM, PHP_BINARY is the FPM binary, so the finder falls back to PHP_BINDIR/php and PATH
        $php = (new PhpExecutableFinder())->find(false);

        if (false === $php) {
            throw new \RuntimeException('No PHP CLI binary found. Set MEMO_DEV_PHP_BINARY.');
        }

        return $php;
    }

    public function getPhpVersion(): string
    {
        $process = new Process([$this->getPhp(), '-r', 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'], null, $this->getEnv(), null, 30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Could not run "%s": %s', $this->getPhp(), trim($process->getErrorOutput())));
        }

        return trim($process->getOutput());
    }

    /**
     * @return list<string>
     */
    public function getComposerCommand(): array
    {
        $composer = $this->composerBinary;

        if ('' === $composer) {
            $composer = (new ExecutableFinder())->find('composer', '', array_filter([$this->getHome() . '/bin']));
        }

        if ('' === $composer || !is_file($composer)) {
            throw new \RuntimeException('No Composer binary found. Set MEMO_DEV_COMPOSER_BINARY.');
        }

        // A "#!/usr/bin/env php" shebang would pick the PATH php, which can be an older version than the site needs
        $handle = fopen($composer, 'r');
        $firstLine = false !== $handle ? (string) fgets($handle) : '';

        if (str_starts_with($firstLine, '#!') && !str_contains($firstLine, 'php')) {
            return [$composer];
        }

        return [$this->getPhp(), $composer];
    }

    /**
     * Composer and git (SSH key) need HOME, which PHP-FPM pools often do not pass on.
     *
     * @return array<string, string>
     */
    public function getEnv(): array
    {
        // SHELL_VERBOSITY leaks from a debug web request and turns every console and composer call into -vvv
        $env = ['GIT_TERMINAL_PROMPT' => '0', 'SHELL_VERBOSITY' => '0'];

        if (!getenv('HOME') && '' !== ($home = $this->getHome())) {
            $env['HOME'] = $home;
        }

        return $env;
    }

    private function getHome(): string
    {
        if ($home = getenv('HOME')) {
            return $home;
        }

        if (\function_exists('posix_getpwuid') && \function_exists('posix_geteuid')) {
            return (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '');
        }

        return '';
    }
}
