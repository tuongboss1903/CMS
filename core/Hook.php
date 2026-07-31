<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Throwable;

/**
 * Hook System kieu WordPress (Action + Filter) tren 1 co che dang ky/uu tien/wildcard dung CHUNG -
 * action va filter chi khac nhau o CACH GOI (do() bo qua gia tri tra ve cho side-effect, apply()
 * truyen gia tri qua tung callback), khong khac o cach dang ky - dung y het cach WordPress lam
 * ben trong (do_action() thuc chat la apply_filters() bo qua ket qua).
 *
 * Khong static/global, khong ham global (add_action()/do_action() kieu thu tuc) - 1 instance Hook
 * song trong dung 1 request (giong Container), inject qua constructor vao Service/Module/Plugin
 * can dang ky hoac bat Hook.
 *
 * Quy uoc dat ten hook: "{module}.{event}" (vd post.before_save, theme.before_render) - xem
 * cms-architecture-proposal.md muc 14.
 *
 * Priority: mac dinh 10, so nho hon chay TRUOC (dung quy uoc WordPress). Cung priority -> chay
 * theo thu tu dang ky.
 *
 * Wildcard: hook dang ky co chua '*' (vd "post.*") khop moi ten hook bat dau "post." khi do()/
 * apply() goi voi ten cu the. Callback wildcard tron chung vao DUNG thu tu priority voi callback
 * dang ky chinh xac, khong chay tach roi truoc/sau.
 *
 * Cach ly loi: moi callback chay trong try/catch RIENG (dung 13-module-plugin.md - "1 plugin loi
 * khong anh huong plugin khac"). Hook KHONG tu log (khong phu thuoc Database/Logger) - chi cung
 * cap diem mo onError() de PluginManager (task sau) tu dang ky ghi log rieng.
 *
 * Han che da biet (giong WordPress that): removeAction()/removeFilter() chi go duoc callback neu
 * truyen dung THAM CHIEU closure/callable da dang ky - 1 closure moi dung code giong het van la
 * object khac, khong go duoc. Phai giu bien tham chieu closure goc neu can go sau nay.
 */
final class Hook
{
    private const DEFAULT_PRIORITY = 10;

    /** @var array<string, array<int, list<callable>>> */
    private array $exact = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $wildcard = [];

    /** @var list<Closure(Throwable, string, callable): void> */
    private array $errorListeners = [];

    public function action(string $hookName, callable $callback, int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->register($hookName, $callback, $priority);
    }

    public function filter(string $hookName, callable $callback, int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->register($hookName, $callback, $priority);
    }

    public function removeAction(string $hookName, callable $callback, int $priority = self::DEFAULT_PRIORITY): bool
    {
        return $this->remove($hookName, $callback, $priority);
    }

    public function removeFilter(string $hookName, callable $callback, int $priority = self::DEFAULT_PRIORITY): bool
    {
        return $this->remove($hookName, $callback, $priority);
    }

    /** Chay tat ca callback dang ky cho $hookName, bo qua gia tri tra ve (dung cho side-effect). */
    public function do(string $hookName, mixed ...$args): void
    {
        foreach ($this->resolve($hookName) as $callback) {
            try {
                $callback(...$args);
            } catch (Throwable $exception) {
                $this->notifyError($exception, $hookName, $callback);
            }
        }
    }

    /**
     * Truyen $value qua tung callback theo thu tu, moi callback nhan gia tri tu callback truoc.
     * Neu 1 callback loi, $value giu nguyen nhu truoc callback do (khong mat du lieu), tiep tuc
     * callback ke tiep trong chuoi.
     */
    public function apply(string $hookName, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->resolve($hookName) as $callback) {
            try {
                $value = $callback($value, ...$args);
            } catch (Throwable $exception) {
                $this->notifyError($exception, $hookName, $callback);
            }
        }

        return $value;
    }

    /** Lang nghe khi 1 callback (action hoac filter) nem loi - xem docblock lop tren. */
    public function onError(Closure $listener): void
    {
        $this->errorListeners[] = $listener;
    }

    private function register(string $hookName, callable $callback, int $priority): void
    {
        if (\str_contains($hookName, '*')) {
            $this->wildcard[$hookName][$priority][] = $callback;

            return;
        }

        $this->exact[$hookName][$priority][] = $callback;
    }

    private function remove(string $hookName, callable $callback, int $priority): bool
    {
        if (\str_contains($hookName, '*')) {
            return $this->removeFrom($this->wildcard, $hookName, $callback, $priority);
        }

        return $this->removeFrom($this->exact, $hookName, $callback, $priority);
    }

    /** @param array<string, array<int, list<callable>>> $registry */
    private function removeFrom(array &$registry, string $hookName, callable $callback, int $priority): bool
    {
        if (!isset($registry[$hookName][$priority])) {
            return false;
        }

        $before = \count($registry[$hookName][$priority]);

        $registry[$hookName][$priority] = \array_values(\array_filter(
            $registry[$hookName][$priority],
            static fn (callable $existing): bool => $existing !== $callback
        ));

        $removed = \count($registry[$hookName][$priority]) < $before;

        if ($registry[$hookName][$priority] === []) {
            unset($registry[$hookName][$priority]);
        }

        if (isset($registry[$hookName]) && $registry[$hookName] === []) {
            unset($registry[$hookName]);
        }

        return $removed;
    }

    /** @return list<callable> */
    private function resolve(string $hookName): array
    {
        /** @var array<int, list<callable>> $byPriority */
        $byPriority = $this->exact[$hookName] ?? [];

        foreach ($this->wildcard as $pattern => $priorities) {
            if (!self::matchesPattern($pattern, $hookName)) {
                continue;
            }

            foreach ($priorities as $priority => $callbacks) {
                $byPriority[$priority] = [...($byPriority[$priority] ?? []), ...$callbacks];
            }
        }

        \ksort($byPriority);

        $ordered = [];

        foreach ($byPriority as $callbacks) {
            foreach ($callbacks as $callback) {
                $ordered[] = $callback;
            }
        }

        return $ordered;
    }

    private static function matchesPattern(string $pattern, string $hookName): bool
    {
        $regex = '/^' . \str_replace('\*', '.*', \preg_quote($pattern, '/')) . '$/';

        return \preg_match($regex, $hookName) === 1;
    }

    private function notifyError(Throwable $exception, string $hookName, callable $callback): void
    {
        foreach ($this->errorListeners as $listener) {
            $listener($exception, $hookName, $callback);
        }
    }
}
