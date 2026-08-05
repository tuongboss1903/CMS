<?php

declare(strict_types=1);

namespace Core;

use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Cache\RedisCacheDriver;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Drivers\LogMailerDriver;
use Core\Mail\Drivers\SmtpMailerDriver;
use Core\Mail\Mailer;
use Core\Mail\MailerDriver;
use Core\Middleware\StartSessionMiddleware;
use Core\Middleware\TenantResolverMiddleware;
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

        // Bind Request that vao Container - phuc vu diem doc lai ($request->path()) tu closure
        // View::class ben duoi (sidebar active-state), khong doi contract handle()/Router.
        $this->container->instance(Request::class, $request);

        $router = $this->container->get(Router::class);

        try {
            return $router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->container->get(ExceptionHandler::class)->handle($exception, $this->isDebug());

            if ($response->getStatusCode() >= 500) {
                $this->logException($exception);
            }

            return $response;
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
        $pluginManager = $this->container->get(PluginManager::class);
        $hook = $this->container->get(Hook::class);

        $hook->onError(function (Throwable $exception, string $hookName, callable $callback): void {
            $this->container->get(Logger::class)->log('error', $exception->getMessage(), [
                'hook' => $hookName,
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        });

        $pluginManager->boot($hook, \array_keys($pluginManager->discover()));

        /*
         * Phase 19 (CMS-056): dong Technical Debt #9 (chua co co che bat/tat Plugin theo tenant).
         * LUU Y KIEN TRUC quan trong tu ban Architecture Analysis: Application::boot() chay TRUOC
         * khi TenantResolverMiddleware resolve tenant cho request hien tai (middleware do chi chay
         * luc $router->dispatch(), sau boot() da xong) - nen enabledKeys truyen vao
         * PluginManager::boot() o TREN KHONG THE loc theo tenant tai thoi diem nay (tenant chua xac
         * dinh duoc). Vi vay PluginManager::boot() giu nguyen hanh vi cu (nap Hooks.php cua MOI
         * plugin da discover, khong doi) - Hooks.php chi dang ky route/hook, tu no khong lo du lieu.
         *
         * Viec bat/tat THAT theo tung tenant duoc enforce o dung thoi diem tenant DA biet: qua
         * Middleware rieng cua tung plugin (vd Plugins\Ecommerce\EcommercePluginGuardMiddleware,
         * dung PluginActivationService da dang o day) gan vao route group cua chinh plugin do trong
         * routes.php cua no - khong sua diem nay them nua.
         *
         * Hook "plugin.routes.register" la diem moi de Hooks.php cua plugin tu nap routes.php cua
         * no (PluginManager::boot() truoc gio CHI nap Hooks.php, khong nap routes.php nhu
         * ModuleManager - Plugin truoc Phase 19 chua co nhu cau dang ky Route).
         *
         * SUA LOI PHASE 20 (tu phat hien truoc khi code, dung Root Cause Analysis - KHONG phai sau
         * khi Owner bao FAIL): dong nay TRUOC DAY goi $hook->do(...) NGOAI group Session+Tenant o
         * tren - Router::group() luu/khoi phuc groupMiddleware quanh closure (xem Router.php::group()
         * dong 115-130), nen khi group cua ModuleManager ket thuc, groupMiddleware da tro ve rong.
         * Moi Route Plugin (routes.php cua Ecommerce) vi vay KHONG duoc bake StartSessionMiddleware/
         * TenantResolverMiddleware vao Route::getMiddleware() - dung y het lo hong da ghi trong
         * docblock StartSessionMiddleware.php ("moi route dung Session that nem SessionException khi
         * chay qua HTTP that, khong bi lo trong test vi test tu goi $session->start() thu cong").
         * Tests Phase 19 khong phat hien duoc vi TOAN BO tu dung Container/Router dung tay, khong di
         * qua Application::boot() that. Sua bang cach boc $hook->do(...) trong CUNG 1 group voi
         * ModuleManager::boot() o tren - Plugin Route tu gio nhan dung Session/Tenant middleware
         * giong het Module Route.
         *
         * SUA LOI ROUTING (phat hien qua kiem thu he thong that CHAY CHUNG Module+Plugin trong 1
         * Router - cac test truoc gio chi dung Container/Router rieng cho tung Module/Plugin nen
         * khong bao gio lo): Router::match() (core/Router.php) khop route THEO DUNG THU TU DANG KY,
         * khong xep hang theo do cu the - dong nay TRUOC DAY nam SAU khoi ModuleManager::boot() nen
         * moi route literal cua Plugin (vd GET /shop, GET /admin/products cua Ecommerce) LUON bi
         * route wildcard cua modules/Public (GET /{slug}, GET /{locale}/{slug}, dang ky truoc do)
         * "nuot" mat truoc khi kip khop - Plugin tra 404 100% thoi gian tren he thong that, du code/
         * test tung Module rieng le deu dung. Fix bang cach dang ky route Plugin TRUOC ModuleManager
         * (doi cho 2 khoi group ben duoi) - Plugin khong tu dinh nghia route wildcard nao nen khong
         * co nguy co nguoc lai (Plugin "nuot" nham route Module).
         */
        /*
         * Buoc 1 (Super Admin - Site/Tenant Management): module "system_admin" phai dang ky
         * TRUOC moi group khac va CHI qua StartSessionMiddleware (KHONG TenantResolverMiddleware) -
         * Super Admin khong thuoc ve 1 site nao nen khong the resolve tenant theo domain nhu
         * cac Module/Plugin con lai. Dang ky truoc de tranh bi route wildcard cua modules/Public
         * (vd GET /{locale}/{slug}) "nuot" mat (dung y het bai hoc rut ra tu loi routing Phase 19
         * o duoi - route literal phai dung truoc route wildcard).
         */
        $discoveredModuleKeys = \array_keys($moduleManager->discover());

        if (\in_array('system_admin', $discoveredModuleKeys, true)) {
            $router->group(['middleware' => [StartSessionMiddleware::class]], function (Router $router) use ($moduleManager): void {
                $moduleManager->boot($router, ['system_admin']);
            });
        }

        $router->group(['middleware' => [StartSessionMiddleware::class, TenantResolverMiddleware::class]], function (Router $router) use ($hook): void {
            $hook->do('plugin.routes.register', $router);
        });

        $router->group(['middleware' => [StartSessionMiddleware::class, TenantResolverMiddleware::class]], function (Router $router) use ($moduleManager, $discoveredModuleKeys): void {
            $moduleManager->boot($router, \array_values(\array_diff($discoveredModuleKeys, ['system_admin'])));
        });

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

        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());

        $this->container->singleton(
            PluginActivationService::class,
            static fn (Container $c): PluginActivationService => new PluginActivationService(
                $c->get(Cache::class),
                $c->get(Database::class)
            )
        );

        $this->container->singleton(View::class, function (Container $c): View {
            $defaultTheme = (string) $c->get(Config::class)->get('app.theme', 'default');
            $tenantManager = $c->get(TenantManager::class);

            $activeTheme = $tenantManager->check()
                ? (string) ($tenantManager->current()['theme_active'] ?? $defaultTheme)
                : $defaultTheme;

            // Phase 19 (CMS-056): diem mo rong "admin.menu.items" - Plugin dang ky filter qua
            // Hooks.php (boot() da chay xong truoc khi View::class lan dau duoc resolve tu 1
            // Controller, dam bao moi filter da dang ky day du). Ket qua duoc dong bang 1 lan/
            // request qua View::$globalData - sidebar.php doc $extra_admin_menu_items ?? [].
            // Truyen them $tenantManager + PluginActivationService qua tham so bo sung cua
            // Hook::apply() (khong doi API Hook) de callback cua Plugin tu quyet dinh co hien thi
            // muc menu hay khong dua theo trang thai bat/tat that cua DUNG tenant hien tai.
            $extraMenuItems = $c->get(Hook::class)->apply(
                'admin.menu.items',
                [],
                $tenantManager,
                $c->get(PluginActivationService::class)
            );

            // Ten user dang nhap that cho Admin Topbar (thay avatar chu "A" tinh) - doc truc tiep
            // tu Session::get('auth.user') (da co san 'name' tu AuthenticationService::attempt()),
            // KHONG phai View tu goi Session - closure nay la noi lap rap qua Container (giong
            // pattern extra_admin_menu_items o tren), View van chi nhan mang du lieu thuan.
            $session = $c->get(Session::class);
            $currentUser = $session->isStarted() ? $session->get('auth.user') : null;
            $currentUserName = \is_array($currentUser) && !empty($currentUser['name'])
                ? (string) $currentUser['name']
                : null;

            // Buoc 5 (Notification UI, CMS-066): badge so thong bao chua doc cho sidebar.php -
            // tinh 1 lan/request giong dung pattern current_user_name o tren (khong qua Module
            // Service de tranh Core phu thuoc Module - chi truy van thang, cung nguyen tac). Bang
            // "notifications" co the chua ton tai o fixture test cu chua migrate them (cung
            // nguyen tac try/catch fallback da dung o Modules\Admin\DashboardController) - bat
            // Throwable, fallback 0.
            $unreadNotificationsCount = 0;
            $userId = $session->isStarted() ? $session->get('auth.user_id') : null;

            if ($userId !== null && $tenantManager->check()) {
                try {
                    $row = $c->get(Database::class)->selectOne(
                        'SELECT COUNT(*) as c FROM notifications WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL',
                        [$tenantManager->id(), $userId]
                    );
                    $unreadNotificationsCount = (int) ($row['c'] ?? 0);
                } catch (Throwable) {
                    // Silent-fail co chu dich - xem docblock o tren.
                }
            }

            // Duong dan request hien tai - phuc vu active-state cua sidebar Admin (so sanh prefix
            // trong sidebar.php). Chi doc, khong ghi - fallback rong neu Request chua duoc bind
            // (vd unit test resolve View::class truc tiep, khong qua Application::handle()).
            $currentPath = '';

            try {
                $currentPath = $c->get(Request::class)->path();
            } catch (Throwable) {
                // Silent-fail co chu dich - xem docblock o tren.
            }

            return new View($this->basePath . '/themes', $activeTheme, $defaultTheme, [
                'extra_admin_menu_items' => $extraMenuItems,
                'current_user_name' => $currentUserName,
                'unread_notifications_count' => $unreadNotificationsCount,
                'current_path' => $currentPath,
            ]);
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

        $this->container->singleton(
            ThemeManager::class,
            fn (): ThemeManager => new ThemeManager($this->basePath . '/themes')
        );

        $this->container->singleton(
            ExceptionHandler::class,
            static fn (): ExceptionHandler => new ExceptionHandler()
        );

        $this->container->singleton(
            Logger::class,
            fn (): Logger => new Logger($this->basePath . '/storage/logs/app.log')
        );

        $this->container->singleton(MailerDriver::class, function (Container $c): MailerDriver {
            $config = $c->get(Config::class);

            if ($config->get('mail.default', 'log') === 'smtp') {
                /** @var array<string, mixed> $smtp */
                $smtp = $config->get('mail.drivers.smtp', []);

                return new SmtpMailerDriver(
                    (string) ($smtp['host'] ?? '127.0.0.1'),
                    (int) ($smtp['port'] ?? 587),
                    $smtp['username'] ?? null,
                    $smtp['password'] ?? null,
                    (string) ($smtp['encryption'] ?? 'tls'),
                    (string) $config->get('mail.from.address', 'no-reply@example.com'),
                    (string) $config->get('mail.from.name', 'CMS')
                );
            }

            return new LogMailerDriver(new Logger((string) $config->get('mail.drivers.log.path', $this->basePath . '/storage/logs/mail.log')));
        });

        $this->container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(MailerDriver::class),
            $c->get(View::class),
            $c->get(Logger::class)
        ));
    }

    private function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    private function logException(Throwable $exception): void
    {
        $this->container->get(Logger::class)->log('error', $exception->getMessage(), [
            'exception_class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => \explode("\n", $exception->getTraceAsString()),
        ]);
    }
}
