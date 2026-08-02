<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Hook;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class HookTest extends TestCase
{
    private Hook $hook;

    protected function setUp(): void
    {
        $this->hook = new Hook();
    }

    public function testActionCallbacksRunInPriorityOrderLowestFirst(): void
    {
        $order = [];

        $this->hook->action('test.event', function () use (&$order): void {
            $order[] = 'priority-20';
        }, 20);
        $this->hook->action('test.event', function () use (&$order): void {
            $order[] = 'priority-5';
        }, 5);
        $this->hook->action('test.event', function () use (&$order): void {
            $order[] = 'priority-10-default';
        });

        $this->hook->do('test.event');

        self::assertSame(['priority-5', 'priority-10-default', 'priority-20'], $order);
    }

    public function testActionCallbacksWithSamePriorityRunInInsertionOrder(): void
    {
        $order = [];

        $this->hook->action('test.event', function () use (&$order): void {
            $order[] = 'first';
        }, 10);
        $this->hook->action('test.event', function () use (&$order): void {
            $order[] = 'second';
        }, 10);

        $this->hook->do('test.event');

        self::assertSame(['first', 'second'], $order);
    }

    public function testFilterThreadsValueThroughEachCallbackInOrder(): void
    {
        $this->hook->filter('test.title', fn (string $title): string => $title . '-A', 10);
        $this->hook->filter('test.title', fn (string $title): string => $title . '-B', 20);

        $result = $this->hook->apply('test.title', 'Hello');

        self::assertSame('Hello-A-B', $result);
    }

    public function testRemoveActionStopsCallbackFromRunning(): void
    {
        $called = false;
        $callback = function () use (&$called): void {
            $called = true;
        };

        $this->hook->action('test.event', $callback);
        $removed = $this->hook->removeAction('test.event', $callback);
        $this->hook->do('test.event');

        self::assertTrue($removed);
        self::assertFalse($called);
    }

    public function testRemoveFilterStopsCallbackFromRunning(): void
    {
        $callback = fn (string $v): string => $v . '-A';

        $this->hook->filter('test.value', $callback);
        $removed = $this->hook->removeFilter('test.value', $callback);
        $result = $this->hook->apply('test.value', 'X');

        self::assertTrue($removed);
        self::assertSame('X', $result);
    }

    public function testRemoveActionReturnsFalseWhenCallbackNotRegistered(): void
    {
        self::assertFalse($this->hook->removeAction('test.event', fn () => null));
    }

    public function testRemovingDifferentClosureInstanceWithSameBodyDoesNotRemoveOriginal(): void
    {
        $called = false;

        $this->hook->action('test.event', function () use (&$called): void {
            $called = true;
        });

        // Closure moi, code giong het nhung KHAC instance - khong xoa duoc (dung nhu WordPress
        // remove_action(): phai giu tham chieu closure goc de go sau nay).
        $removed = $this->hook->removeAction('test.event', function () use (&$called): void {
            $called = true;
        });

        $this->hook->do('test.event');

        self::assertFalse($removed);
        self::assertTrue($called);
    }

    public function testWildcardActionMatchesMultipleHookNames(): void
    {
        $order = [];

        $this->hook->action('post.*', function () use (&$order): void {
            $order[] = 'wildcard';
        });

        $this->hook->do('post.before_save');
        $this->hook->do('post.after_save');
        $this->hook->do('page.before_save');

        self::assertSame(['wildcard', 'wildcard'], $order);
    }

    public function testWildcardCallbackRunsInCorrectPriorityOrderWithExactCallbacks(): void
    {
        $order = [];

        $this->hook->action('post.before_save', function () use (&$order): void {
            $order[] = 'exact-20';
        }, 20);
        $this->hook->action('post.*', function () use (&$order): void {
            $order[] = 'wildcard-5';
        }, 5);

        $this->hook->do('post.before_save');

        self::assertSame(['wildcard-5', 'exact-20'], $order);
    }

    public function testDoOnHookWithNoRegisteredCallbacksDoesNothing(): void
    {
        // do() tra ve void - assertNull() la assertion THAT (khong phai gia vo), chung minh
        // do() chay xong khong nem loi khi hook rong (khac voi @doesNotPerformAssertions vốn
        // khong thuc su kiem tra gi).
        self::assertNull($this->hook->do('does.not.exist'));
    }

    public function testApplyOnHookWithNoRegisteredCallbacksReturnsOriginalValue(): void
    {
        self::assertSame('unchanged', $this->hook->apply('does.not.exist', 'unchanged'));
    }

    public function testExceptionInOneActionCallbackDoesNotStopOtherCallbacks(): void
    {
        $ran = [];

        $this->hook->action('test.event', function () use (&$ran): void {
            $ran[] = 'first';

            throw new RuntimeException('boom');
        }, 10);
        $this->hook->action('test.event', function () use (&$ran): void {
            $ran[] = 'second';
        }, 20);

        $this->hook->do('test.event');

        self::assertSame(['first', 'second'], $ran);
    }

    public function testExceptionInOneFilterCallbackKeepsPreviousValueAndContinues(): void
    {
        $this->hook->filter('test.value', fn (string $v): string => $v . '-A', 10);
        $this->hook->filter('test.value', function (string $v): string {
            throw new RuntimeException('boom');
        }, 20);
        $this->hook->filter('test.value', fn (string $v): string => $v . '-C', 30);

        $result = $this->hook->apply('test.value', 'X');

        self::assertSame('X-A-C', $result);
    }

    public function testOnErrorListenerReceivesExceptionHookNameAndCallback(): void
    {
        $captured = null;

        $this->hook->onError(function (Throwable $exception, string $hookName, callable $callback) use (&$captured): void {
            $captured = [$exception->getMessage(), $hookName];
        });

        $this->hook->action('test.event', function (): void {
            throw new RuntimeException('specific-error');
        });

        $this->hook->do('test.event');

        self::assertSame(['specific-error', 'test.event'], $captured);
    }

    public function testDoPassesArgumentsToCallback(): void
    {
        $received = null;

        $this->hook->action('post.published', function (string $title, int $id) use (&$received): void {
            $received = [$title, $id];
        });

        $this->hook->do('post.published', 'Hello World', 5);

        self::assertSame(['Hello World', 5], $received);
    }

    public function testApplyPassesExtraArgumentsAlongsideValue(): void
    {
        $this->hook->filter(
            'post.title',
            fn (string $title, int $postId): string => $title . " (#{$postId})"
        );

        $result = $this->hook->apply('post.title', 'Hello', 5);

        self::assertSame('Hello (#5)', $result);
    }
}
