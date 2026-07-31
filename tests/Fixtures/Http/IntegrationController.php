<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;

/** Dung cho Regression Test: chung minh Router ghep dung Container + Database + View. */
final class IntegrationController
{
    public function __construct(private readonly Database $database, private readonly View $view)
    {
    }

    public function index(Request $request): Response
    {
        $rows = $this->database->select('SELECT 1 as one');
        $viewOutput = trim($this->view->render('greeting'));

        return Response::html($viewOutput . '|' . $rows[0]['one']);
    }
}
