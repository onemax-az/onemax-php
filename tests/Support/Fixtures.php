<?php

declare(strict_types=1);

namespace OneMax\Tests\Support;

final class Fixtures
{
    public const API_KEY = 'omx_live_test';

    public const CONTEXT = '{"partner_name":"Kofe Evi","venue_id":"ven_1","venue_name":"Kofe Evi Nizami",'
        . '"daily_limit":2,"extra":"ignored","offers":[{"id":"off_1","headline":"1+1 kofe","title":"Kofe",'
        . '"open_now":true,"extra":1},{"id":"off_2","headline":"1+1 desert","title":"Desert","open_now":false}]}';

    public const VERIFICATION_USABLE = '{"active":true,"usable":true,"reason":"ok","usage_id":"usg_1","daily_limit":2}';

    public const VERIFICATION_REFUSED = '{"active":false,"usable":false,"reason":"not_subscribed","usage_id":null,'
        . '"daily_limit":2}';

    public const USAGE_PENDING = '{"usage_id":"usg_1","status":"pending","reference":null,'
        . '"created_at":"2026-10-07T17:16:11.906636Z","confirmed_at":null,"voided_at":null}';

    public const USAGE_CONFIRMED = '{"usage_id":"usg_1","status":"confirmed","reference":"order-42",'
        . '"created_at":"2026-10-07T17:16:11.906636Z","confirmed_at":"2026-10-07T21:20:00+04:00","voided_at":null}';

    public const USAGE_VOIDED = '{"usage_id":"usg_1","status":"voided","reference":null,'
        . '"created_at":"2026-10-07T17:16:11.906636Z","confirmed_at":null,"voided_at":"2026-10-07T17:18:00Z"}';

    public const LINK_TOKEN = '{"link_token":"omx_link_abc","active":true}';

    public const LINK_STATUS = '{"linked":true,"active":false}';
}
