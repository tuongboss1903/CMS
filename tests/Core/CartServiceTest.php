<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Session;
use PHPUnit\Framework\TestCase;
use Plugins\Ecommerce\Services\CartService;

/** Phase 19 (Ecommerce MVP, CMS-056). Session that (khong mock), dung tien le SessionTest.php. */
final class CartServiceTest extends TestCase
{
    private Session $session;
    private CartService $cartService;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();

        $this->cartService = new CartService($this->session);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testEmptyCartHasNoItemsAndZeroTotal(): void
    {
        self::assertTrue($this->cartService->isEmpty());
        self::assertSame([], $this->cartService->items());
        self::assertSame(0.0, $this->cartService->total());
    }

    public function testAddInsertsNewItem(): void
    {
        $this->cartService->add(1, null, 'Ao thun', 100.0, 2);

        $items = $this->cartService->items();
        self::assertCount(1, $items);
        self::assertSame(2, $items['1:0']['quantity']);
        self::assertFalse($this->cartService->isEmpty());
    }

    public function testAddSameProductTwiceAccumulatesQuantity(): void
    {
        $this->cartService->add(1, null, 'Ao thun', 100.0, 2);
        $this->cartService->add(1, null, 'Ao thun', 100.0, 3);

        $items = $this->cartService->items();
        self::assertCount(1, $items);
        self::assertSame(5, $items['1:0']['quantity']);
    }

    public function testAddDifferentVariantsAreSeparateLines(): void
    {
        $this->cartService->add(1, 10, 'Ao thun - Do', 100.0, 1);
        $this->cartService->add(1, 20, 'Ao thun - Xanh', 100.0, 1);

        self::assertCount(2, $this->cartService->items());
    }

    public function testRemoveDeletesLine(): void
    {
        $this->cartService->add(1, null, 'Ao thun', 100.0, 1);
        $this->cartService->remove(1, null);

        self::assertTrue($this->cartService->isEmpty());
    }

    public function testTotalSumsAllLines(): void
    {
        $this->cartService->add(1, null, 'Ao thun', 100.0, 2);
        $this->cartService->add(2, null, 'Quan jean', 200.0, 1);

        self::assertSame(400.0, $this->cartService->total());
    }

    public function testClearEmptiesCart(): void
    {
        $this->cartService->add(1, null, 'Ao thun', 100.0, 1);
        $this->cartService->clear();

        self::assertTrue($this->cartService->isEmpty());
    }
}
