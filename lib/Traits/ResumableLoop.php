<?php

/**
 * Loop helper trait.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2020 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace danog\Loop\Traits;

use Amp\Deferred;
use Amp\Loop as AmpLoop;
use Amp\Promise;
use Amp\Success;
use Closure;

/**
 * Resumable loop helper trait.
 *
 * @author Daniil Gentili <daniil@daniil.it>
 */
trait ResumableLoop
{
    use Loop {
        exitedLoop as parentExitedLoop;
    }
    /**
     * Resume deferred.
     *
     * @var ?Deferred
     */
    private $resume;
    /**
     * Pause deferred.
     *
     * @var ?Deferred
     */
    private $pause;
    /**
     * Resume timer ID.
     *
     * @var ?string
     */
    private $resumeTimer;
    /**
     * Pause the loop.
     *
     * @param int|float|null $time For how long to pause the loop, if null will pause forever (until resume is called from outside of the loop)
     *
     * @return Promise Resolved when the loop is resumed
     */
    public function pause($time = null): Promise
    {
        if (!\is_null($time)) {
            if ($time <= 0) {
                return new Success(0);
            }
            if ($this->resumeTimer) {
                AmpLoop::cancel($this->resumeTimer);
                $this->resumeTimer = null;
            }
            $this->resumeTimer = AmpLoop::delay((int) ($time * 1000), \Closure::fromCallable([$this, 'resumeInternal']));
        }
        $this->resume = new Deferred();
        $pause = $this->pause;
        $this->pause = new Deferred();
        if ($pause) {
            /**
             * @psalm-suppress InvalidArgument
             */
            AmpLoop::defer([$pause, 'resolve']);
        }
        return $this->resume->promise();
    }
    /**
     * Resume the loop.
     *
     * @return Promise Resolved when the loop is paused again
     */
    public function resume(): Promise
    {
        $this->resumeInternal();
        if (!$this->pause) {
            $this->pause = new Deferred;
        }
        return $this->pause->promise();
    }
    /**
     * Defer resuming the loop.
     *
     * @return Promise Resolved when the loop is paused again
     */
    public function resumeDefer(): Promise
    {
        AmpLoop::defer(Closure::fromCallable([$this, 'resumeInternal']));
        if (!$this->pause) {
            $this->pause = new Deferred;
        }
        return $this->pause->promise();
    }
    /**
     * Internal resume function.
     *
     * @return void
     */
    private function resumeInternal(): void
    {
        if ($this->resumeTimer) {
            $storedWatcherId = $this->resumeTimer;
            AmpLoop::cancel($storedWatcherId);
            $this->resumeTimer = null;
        }
        if ($this->resume) {
            $resume = $this->resume;
            $this->resume = null;
            $resume->resolve();
        }
    }

    /**
     * Signal that loop has exIited.
     *
     * @return void
     */
    protected function exitedLoop(): void
    {
        $this->parentExitedLoop();
        if ($this->resumeTimer) {
            AmpLoop::cancel($this->resumeTimer);
            $this->resumeTimer = null;
        }
    }
}
