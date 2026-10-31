<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * The Faccelerate API could not be reached, or answered with an error.
 * The message is safe to show to the admin user (it never contains credentials).
 */
class ApiException extends LocalizedException
{
}
