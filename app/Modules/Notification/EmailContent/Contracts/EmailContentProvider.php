<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Contracts;

use App\Shared\Contracts\Notification\EmailContentProvider as SharedEmailContentProvider;

/**
 * Back-compat shim. Concrete providers inside the Notification module continue
 * to implement this interface; new cross-module consumers should depend on
 * {@see SharedEmailContentProvider} directly.
 */
interface EmailContentProvider extends SharedEmailContentProvider {}
