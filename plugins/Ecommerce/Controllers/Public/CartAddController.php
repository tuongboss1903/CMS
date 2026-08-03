<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Plugins\Ecommerce\Actions\AddToCartAction;
use Plugins\Ecommerce\Actions\EcommerceValidationException;

/** POST /cart/add */
final class CartAddController
{
    public function __construct(
        private readonly AddToCartAction $action,
        private readonly Session $session,
    ) {
    }

    public function handle(Request $request): Response
    {
        $data = $request->all();
        $productId = (int) ($data['product_id'] ?? 0);
        $variantId = !empty($data['variant_id']) ? (int) $data['variant_id'] : null;
        $quantity = (int) ($data['quantity'] ?? 1);

        try {
            $this->action->execute($productId, $variantId, $quantity);
        } catch (EcommerceValidationException $exception) {
            $this->session->flash('cart_error', $exception->getMessage());

            return Response::redirect('/shop');
        }

        $this->session->flash('flash_success', 'Da them vao gio hang.');

        return Response::redirect('/cart');
    }
}
