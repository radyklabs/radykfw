<?php declare (strict_types=1);

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

return [
    // Uncomment to override only the RadykFW admin locale: 'locale' => 'id_ID',
    // Manual runtime overrides are read-only to the Settings UI:
    // 'overrides' => [
    //     'routing' => ['native' => false],
    //     'modules' => ['auto_discover' => true],
    // ],
];
