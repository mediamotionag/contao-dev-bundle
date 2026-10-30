<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Tests\Unit\VersionSwitch;

use Memo\DevBundle\VersionSwitch\RunFormatter;
use Memo\DevBundle\VersionSwitch\StatusStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class RunFormatterTest extends TestCase
{
    private string $projectDir;

    private RunFormatter $formatter;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/memo-dev-' . bin2hex(random_bytes(4));

        $translator = new class() implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id;
            }

            public function getLocale(): string
            {
                return 'de';
            }
        };

        $this->formatter = new RunFormatter($translator, new StatusStore($this->projectDir));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testSuccessfulRunIsComplete(): void
    {
        $result = $this->formatter->steps(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'step' => 'cache']);

        $this->assertSame(['done', 'done', 'done', 'done', 'done'], array_column($result['steps'], 'state'));
        $this->assertSame(100, $result['progress']);
    }

    public function testPreflightFailureShowsNoRevert(): void
    {
        $result = $this->formatter->steps(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_UNCHANGED, 'failedStep' => 'preflight']);

        $this->assertNotContains('revert', array_column($result['steps'], 'key'));
        $this->assertSame('failed', $result['steps'][0]['state']);
    }

    public function testRevertedComposerFailure(): void
    {
        $result = $this->formatter->steps(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_REVERTED, 'failedStep' => 'composer']);

        $this->assertSame([
            ['key' => 'preflight', 'state' => 'done'],
            ['key' => 'checkout', 'state' => 'done'],
            ['key' => 'composer', 'state' => 'failed'],
            ['key' => 'migrate', 'state' => 'skipped'],
            ['key' => 'cache', 'state' => 'skipped'],
            ['key' => 'revert', 'state' => 'done'],
        ], $result['steps']);
    }

    public function testFailedRevert(): void
    {
        $result = $this->formatter->steps(['action' => 'switch', 'state' => StatusStore::STATE_FAILED, 'failedStep' => 'migrate']);

        $this->assertSame(['key' => 'revert', 'state' => 'failed'], end($result['steps']));
    }

    public function testRestoreHasItsOwnStepsAndNoRevert(): void
    {
        $result = $this->formatter->steps(['action' => 'restore', 'state' => StatusStore::STATE_FAILED, 'failedStep' => 'restore']);

        $this->assertSame(['restore', 'cache'], array_column($result['steps'], 'key'));
    }

    public function testRunningStepIsActiveAndProgressStaysBelowComplete(): void
    {
        $result = $this->formatter->steps([
            'action' => 'switch',
            'state' => StatusStore::STATE_RUNNING,
            'running' => true,
            'step' => 'composer',
            'stepTimes' => ['composer' => microtime(true) - 10000],
        ]);

        $this->assertSame(['done', 'done', 'active', 'pending', 'pending'], array_column($result['steps'], 'state'));
        $this->assertLessThanOrEqual(99.0, $result['progress']);
        $this->assertGreaterThan(0.0, $result['progress']);
    }

    #[DataProvider('refProvider')]
    public function testDisplayRef(string $ref, string $expected): void
    {
        $this->assertSame($expected, RunFormatter::displayRef($ref));
    }

    public static function refProvider(): iterable
    {
        yield 'tag ref' => ['refs/tags/v4.3.2', 'v4.3.2'];
        yield 'plain tag' => ['v4.3.2', 'v4.3.2'];
        yield 'branch' => ['master', 'master'];
    }
}
