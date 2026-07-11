<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Queries\Dng\ListDngReceiptExceptionsQuery;
use Inertia\Inertia;
use Inertia\Response;

class DngReceiptExceptionController extends Controller
{
    public function index(ListDngReceiptExceptionsQuery $query): Response
    {
        return Inertia::render('Finance/Payments/DngReceiptExceptions/Index', [
            'items' => $query->handle(),
        ]);
    }
}
