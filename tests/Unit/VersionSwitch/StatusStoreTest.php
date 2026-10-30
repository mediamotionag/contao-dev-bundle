<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Tests\Unit\VersionSwitch;

use Memo\DevBundle\VersionSwitch\StatusStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class StatusStoreTest extends TestCase
{
    private string $projectDir;

    private StatusStore $store;

    private int $runCounter = 0;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/memo-dev-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->projectDir . '/var/backups');
        $this->store = new StatusStore($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testNoRunsOfferNoRestore(): void
    {
        $this->assertSame(['backup' => null, 'reason' => 'none'], $this->store->getRestoreState());
    }

    public function testSuccessfulSwitchOffersNoRestore(): void
    {
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'backup' => $this->backupFile('a')]);

        $this->assertSame(['backup' => null, 'reason' => 'none'], $this->store->getRestoreState());
    }

    public function testSwitchFailedWithNewCodeCheckedOutOffersNoRestore(): void
    {
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED, 'checkedOut' => true, 'backup' => $this->backupFile('a')]);

        $this->assertSame(['backup' => null, 'reason' => 'none'], $this->store->getRestoreState());
    }

    public function testRevertedSwitchOffersItsOwnBackup(): void
    {
        $backup = $this->backupFile('a');
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_REVERTED, 'backup' => $backup]);

        $this->assertSame(['backup' => $backup, 'reason' => null], $this->store->getRestoreState());
    }

    public function testRevertedSwitchWithoutMigration(): void
    {
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_REVERTED, 'backup' => null]);

        $this->assertSame(['backup' => null, 'reason' => 'no_migration'], $this->store->getRestoreState());
    }

    public function testRevertedSwitchWithDeletedBackup(): void
    {
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_REVERTED, 'backup' => 'backup__gone.sql.gz']);

        $this->assertSame(['backup' => null, 'reason' => 'missing'], $this->store->getRestoreState());
    }

    public function testSuccessfulRollbackOffersTheBackupOfTheSwitchItUndid(): void
    {
        $backup = $this->backupFile('a');
        $switch = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'backup' => $backup]);
        $this->addRun(['action' => 'rollback', 'state' => StatusStore::STATE_SUCCESS, 'rollbackOf' => $switch, 'backup' => null]);

        $this->assertSame(['backup' => $backup, 'reason' => null], $this->store->getRestoreState());
    }

    public function testRevertedRollbackOffersNoRestore(): void
    {
        $switch = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'backup' => $this->backupFile('a')]);
        $this->addRun(['action' => 'rollback', 'state' => StatusStore::STATE_FAILED_REVERTED, 'rollbackOf' => $switch]);

        $this->assertSame(['backup' => null, 'reason' => 'none'], $this->store->getRestoreState());
    }

    public function testRestoredBackupIsNotOfferedAgain(): void
    {
        $backup = $this->backupFile('a');
        $switch = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'backup' => $backup]);
        $this->addRun(['action' => 'rollback', 'state' => StatusStore::STATE_SUCCESS, 'rollbackOf' => $switch]);
        $this->addRun(['action' => 'restore', 'state' => StatusStore::STATE_SUCCESS, 'backup' => $backup]);

        $this->assertSame(['backup' => null, 'reason' => 'restored'], $this->store->getRestoreState());
    }

    public function testUnchangedRunsDoNotHideTheRestore(): void
    {
        $backup = $this->backupFile('a');
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_REVERTED, 'backup' => $backup]);
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_UNCHANGED]);

        $this->assertSame($backup, $this->store->getRestoreBackup());
    }

    public function testRollbackTargetIsTheLatestSuccessfulSwitch(): void
    {
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'tag' => 'v1', 'previousRef' => 'refs/tags/v0']);
        $latest = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS, 'tag' => 'v2', 'previousRef' => 'refs/tags/v1']);
        $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED_UNCHANGED, 'tag' => 'v3', 'previousRef' => 'refs/tags/v2']);

        $this->assertSame($latest, $this->store->getRollbackTarget()['runId'] ?? null);
    }

    public function testRunToTheSameVersionIsNoRollbackTarget(): void
    {
        $this->addRun(['action' => 'rollback', 'state' => StatusStore::STATE_SUCCESS, 'tag' => 'refs/tags/v1', 'previousRef' => 'refs/tags/v1']);

        $this->assertNull($this->store->getRollbackTarget());
    }

    public function testFailedSwitchStillCheckedOutIsARollbackTarget(): void
    {
        $failed = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_FAILED, 'checkedOut' => true, 'tag' => 'v2', 'previousRef' => 'refs/tags/v1']);

        $this->assertSame($failed, $this->store->getRollbackTarget()['runId'] ?? null);
    }

    public function testDeadRunningJobIsReportedAsAborted(): void
    {
        $runId = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_RUNNING]);

        $run = $this->store->getRunState($runId);

        $this->assertSame(StatusStore::STATE_ABORTED, $run['state']);
        $this->assertFalse($run['running']);
    }

    public function testReadLogReturnsOnlyAppendedBytes(): void
    {
        $runId = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS]);
        $this->store->appendLog($runId, "first\n");

        $first = $this->store->readLog($runId, 0);
        $this->store->appendLog($runId, "second\n");
        $second = $this->store->readLog($runId, $first['offset']);

        $this->assertSame("first\n", $first['log']);
        $this->assertSame("second\n", $second['log']);
        $this->assertSame(13, $second['offset']);
    }

    public function testReadLogStartsOverWhenTheLogWasReplaced(): void
    {
        $runId = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS]);
        $this->store->appendLog($runId, "new\n");

        $this->assertSame("new\n", $this->store->readLog($runId, 500)['log']);
    }

    public function testGetRunOffsetMatchesTheLogItReturns(): void
    {
        $runId = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS]);
        $this->store->appendLog($runId, "line\n");

        $run = $this->store->getRun($runId);

        $this->assertSame("line\n", $run['log']);
        $this->assertSame(5, $run['logOffset']);
    }

    public function testInvalidRunIdIsRejected(): void
    {
        $this->assertFalse($this->store->isValidRunId('../../config'));
        $this->assertNull($this->store->getRun('../../config'));

        $this->expectException(\InvalidArgumentException::class);
        $this->store->getPath('../../config', 'json');
    }

    public function testLockIsExclusiveAcrossStores(): void
    {
        $other = new StatusStore($this->projectDir);

        $this->assertTrue($this->store->lock());
        $this->assertFalse($other->lock());
        $this->assertTrue($other->isLocked());
    }

    public function testIsLockedReleasesItsProbe(): void
    {
        $other = new StatusStore($this->projectDir);

        $this->assertFalse($this->store->isLocked());
        $this->assertTrue($other->lock());
    }

    public function testPruneKeepsTheTenNewestRuns(): void
    {
        $runIds = [];

        for ($i = 0; $i < 12; ++$i) {
            $runIds[] = $this->addRun(['action' => 'switch', 'state' => StatusStore::STATE_SUCCESS]);
        }

        $this->store->prune();
        $kept = array_column($this->store->getRuns(), 'runId');

        $this->assertCount(10, $kept);
        $this->assertNotContains($runIds[0], $kept);
        $this->assertNotContains($runIds[1], $kept);
    }

    /**
     * Run IDs sort by time; the counter keeps runs added in one test in order.
     */
    private function addRun(array $status): string
    {
        $runId = sprintf('20261030-%06d-%06x', ++$this->runCounter, $this->runCounter);
        $this->store->write($runId, ['runId' => $runId, 'startedAt' => date(DATE_ATOM)] + $status);

        return $runId;
    }

    private function backupFile(string $name): string
    {
        $file = 'backup__' . $name . '.sql.gz';
        touch($this->projectDir . '/var/backups/' . $file);

        return $file;
    }
}
