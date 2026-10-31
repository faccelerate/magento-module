<?php
declare(strict_types=1);

namespace Faccelerate\Connect\Model\Connection;

/**
 * The four OAuth1 values of the Faccelerate integration. The access token pair is
 * empty when the integration has no access token (yet).
 */
final class Credentials
{
    public function __construct(
        public readonly string $consumerKey,
        public readonly string $consumerSecret,
        public readonly string $accessToken = '',
        public readonly string $accessTokenSecret = ''
    ) {
    }
}
