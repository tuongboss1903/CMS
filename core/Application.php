<?php

declare(strict_types=1);

namespace Core;

use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Cache\RedisCacheDriver;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\MethodNotAllowedException;
use Core\Router\RouteNotFoundException;
use Throwable;

/**
 * Diem khoi dong DUY NHAT cua framework - rap toan bo Core Component (Config -> ModuleManager)
 * thanh 1 pipeline chay duoc that. KHONG sua API cua bat ky Core Component nao truoc do, chi
 * DANG KY chung vao Container.
 *
 * handle(Request): Response la phan THUAN (test duoc, khong dung superglobal that). run(): void
 * la I/O boundary duy nhat - Request::fromGlobals() (doc superglobal) + Response::send() (xuat
 * output that) - cung triet ly da ap dung cho Router::dispatch() (tinh toan) vs Response::send().
 */
final class Application
{
    private bool $booted = false;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly string $basePath,
    ) {
        $this->registerCoreServices();
    }

    public static function bootstrap(string $basePath): self
    {
        $config = new Config($basePath . '/config');
        $container = new Container();

        return new self($config, $container, $basePath);
    }

    /**
     * Bo sung ngoai thiet ke da duyet ban dau (chi co bootstrap/handle/run) - can thiet de test
     * xac nhan Core Service dang ky dung vao Container ma khong phai goi qua 1 route that.
     */
    public function container(): Container
    {
        return $this->container;
    }

    public function handle(Request $request): Response
    {
        $this->boot();

        $router = $this->container->get(Router::class);

        try {
            return $router->dispatch($request);
        } catch (RouteNotFoundException) {
            return $this->errorResponse(404, 'Not Found');
        } catch (MethodNotAllowedException) {
            return $this->errorResponse(405, 'Method Not Allowed');
        } catch (Throwable $exception) {
            $this->logException($exception);

            return $this->errorResponse(
                500,
                $this->isDebug() ? $exception->getMessage() : 'Internal Server Error'
            );
        }
    }

    public function run(): void
    {
        $this->handle(Request::fromGlobals())->send();
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $moduleManager = $this->container->get(ModuleManager::class);
        $router = $this->container->get(Router::class);

        $moduleManager->boot($router, \array_keys($moduleManager->discover()));

        $pluginManager = $this->container->get(PluginManager::class);
        $hook = $this->container->get(Hook::class);

        $pluginManager->boot($hook, \array_keys($pluginManager->discover()));

        $router->get('/health', static fn (Request $request): Response => Response::json([
            'success' => true,
            'data' => ['status' => 'ok'],
            'message' => '',
            'errors' => [],
        ]));

        $this->booted = true;
    }

    private function registerCoreServices(): void
    {
        $this->container->instance(Config::class, $this->config);

        $this->container->singleton(
            Database::class,
            static fn (Container $c): Database => new Database($c->get(Config::class))
        );

        $this->container->singleton(
            Session::class,
            static fn (Container $c): Session => new Session($c->get(Config::class))
        );

        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());

        $this->container->singleton(CacheDriver::class, static function (Container $c): CacheDriver {
            $config = $c->get(Config::class);

            if ($config->get('cache.default', 'file') === 'redis') {
                /** @var array{host: string, port: int, password: string|null, database: int} $redisConfig */
                $redisConfig = $config->get('cache.drivers.redis', []);

                return new RedisCacheDriver($redisConfig);
            }

            return new FileCacheDriver((string) $config->get('cache.drivers.file.path'));
        });

        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache(
            $c->get(CacheDriver::class),
            (string) $c->get(Config::class)->get('cache.prefix', '')
        ));

        $this->container->singleton(View::class, function (Container $c): View {
            $theme = (string) $c->get(Config::class)->get('app.theme', 'default');

            return new View($this->basePath . '/themes', $theme, $theme);
        });

        $this->container->singleton(Router::class, static fn (Container $c): Router => new Router($c));

        $this->container->singleton(
            ModuleManager::class,
            fn (): ModuleManager => new ModuleManager($this->basePath . '/modules')
        );

        $this->container->singleton(
            PluginManager::class,
            fn (): PluginManager => new PluginManager($this->basePath . '/plugins')
        );
    }

    private function errorResponse(int $status, string $message): Response
    {
        return Response::json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => [],
        ], $status);
    }

    private function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    private function logException(Throwable $exception): void
    {
        $logPath = $this->basePath . '/storage/logs';

        if (!\is_dir($logPath)) {
            @\mkdir($logPath, 0775, true);
        }

        $line = \sprintf(
            '[%s] %s: %s in %s:%d%s',
            \date('Y-m-d H:i:s'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL
        );

        @\file_put_contents($logPath . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
