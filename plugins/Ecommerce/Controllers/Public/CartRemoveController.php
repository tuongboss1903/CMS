<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Http\Request;
use Core\Http\Response;
use Plugins\Ecommerce\Actions\RemoveFromCartAction;

/** POST /cart/remove */
final class CartRemoveController
{
    public function __construct(private readonly RemoveFromCartAction $action)
    {
    }

    public function handle(Request $request): Response
    {
        $data = $request->all();
        $productId = (int) ($data['product_id'] ?? 0);
        $variantId = !empty($data['variant_id']) ? (int) $data['variant_id'] : null;

        $this->action->execute($productId, $variantId);

        return Response::redirect('/cart');
    }
}
