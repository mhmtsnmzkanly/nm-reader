#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Generate openapi.json from canonical API specification.
 * Usage: php app/Console/generate_openapi.php
 */

$basePath = dirname(__DIR__, 2);

$endpoints = [
    // 1. Content & Discovery
    ['GET', '/home', 'Homepage Aggregated Feed', 'Content', false],
    ['GET', '/genres', 'List Genres', 'Taxonomy', false],
    ['GET', '/tags', 'List Tags', 'Taxonomy', false],
    ['GET', '/content/type/{type}', 'Browse Series by Content Type', 'Content', false],
    ['GET', '/content/{type}/chapters', 'Latest Chapters by Type', 'Content', false],
    ['GET', '/content/{type}/{slug}', 'Series Detail by Type and Slug', 'Content', false],
    ['GET', '/content/{type}/{slug}/chapters', 'List Chapters of Series', 'Content', false],
    ['GET', '/latest-chapters', 'Global Latest Chapters Feed', 'Content', false],
    ['GET', '/shop/packages', 'Coin Packages Catalogue', 'Shop', false],
    ['GET', '/shop/features', 'Premium Feature Packages', 'Shop', false],
    ['GET', '/series_genres', 'Genres List Alias', 'Taxonomy', false],
    ['GET', '/series_tags', 'Tags List Alias', 'Taxonomy', false],
    ['GET', '/genre/{slug}', 'Series Filtered by Genre', 'Taxonomy', false],
    ['GET', '/tag/{slug}', 'Series Filtered by Tag', 'Taxonomy', false],
    ['GET', '/content/{type}/{slug}/chapter/{chapterNumber}', 'Reader Chapter Content', 'Reader', false],

    // 2. Comments & Interaction
    ['GET', '/chapter/{chapterId}/comments', 'List Chapter Comments', 'Comments', false],
    ['GET', '/content/{type}/{slug}/comments', 'List Series Comments', 'Comments', false],
    ['GET', '/blogs/{slug}/comments', 'List Blog Comments', 'Comments', false],
    ['POST', '/content/{type}/{slug}/rate', 'Rate Series', 'Interactions', true],
    ['POST', '/content/{type}/{slug}/comment', 'Create Series Comment', 'Comments', true],
    ['POST', '/chapter/{chapterId}/comment', 'Create Chapter Comment', 'Comments', true],
    ['POST', '/comments/{commentId}/vote', 'Vote on Comment', 'Comments', true],
    ['POST', '/blogs/{slug}/comments', 'Create Blog Comment', 'Comments', true],
    ['POST', '/blogs/{slug}/comments/{commentId}/vote', 'Vote on Blog Comment', 'Comments', true],

    // 3. Social & Follows
    ['POST', '/content/{type}/{slug}/follow', 'Follow Series', 'Social', true],
    ['DELETE', '/content/{type}/{slug}/follow', 'Unfollow Series', 'Social', true],
    ['GET', '/user/follows', 'List Followed Series', 'Social', true],
    ['GET', '/user/follows/users', 'List Followed Users', 'Social', true],
    ['POST', '/user/follows/{person}', 'Follow User Profile', 'Social', true],
    ['DELETE', '/user/follows/{person}', 'Unfollow User Profile', 'Social', true],

    // 4. Blogs
    ['GET', '/blogs', 'List Published Blogs', 'Blog', false],
    ['GET', '/blogs/{slug}', 'Blog Post Detail', 'Blog', false],
    ['POST', '/blogs', 'Create Blog Post', 'Blog', true],
    ['POST', '/blogs/image', 'Upload Blog Image', 'Blog', true],
    ['POST', '/blogs/{slug}/vote', 'Vote on Blog Post', 'Blog', true],
    ['GET', '/user/blogs', 'List Authenticated User Blogs', 'Blog', true],

    // 5. Search & Discovery
    ['GET', '/search', 'Filtered Series Search', 'Search', false],
    ['GET', '/search/suggest', 'Live Search Suggestions', 'Search', false],
    ['GET', '/i18n/{lang}', 'Translations Dictionary', 'System', false],
    ['POST', '/log/error', 'Client Error Logging', 'System', false],
    ['POST', '/user/activity', 'Track User Activity', 'Analytics', true],
    ['GET', '/profile/{person}', 'Public User Profile', 'User', false],

    // 6. Auth & Sessions
    ['POST', '/auth/register', 'Register User', 'Auth', false],
    ['POST', '/auth/login', 'User Login', 'Auth', false],
    ['POST', '/auth/refresh', 'Refresh Session Token', 'Auth', false],
    ['GET', '/auth/csrf', 'Get Fresh CSRF Token', 'Auth', false],
    ['POST', '/auth/logout', 'User Logout', 'Auth', false],
    ['GET', '/auth/sessions', 'List Active Device Sessions', 'Auth', true],
    ['DELETE', '/auth/sessions/{sessionKey}', 'Revoke Specific Session', 'Auth', true],
    ['POST', '/auth/sessions/revoke-others', 'Revoke Other Sessions', 'Auth', true],

    // 7. User Profile & Wallet
    ['GET', '/user/profile', 'Get User Profile', 'User', true],
    ['POST', '/user/profile', 'Update User Profile', 'User', true],
    ['GET', '/user/history', 'Get Reading History', 'User', true],
    ['POST', '/user/history', 'Record Reading Progress', 'User', true],
    ['DELETE', '/user/history/{historyId}', 'Delete Reading History Entry', 'User', true],
    ['DELETE', '/user/history', 'Clear Reading History', 'User', true],
    ['GET', '/user/preferences', 'Get Reading Preferences', 'User', true],
    ['PUT', '/user/preferences', 'Update Reading Preferences', 'User', true],
    ['GET', '/user/wallet', 'Get Wallet Balance', 'Wallet', true],
    ['GET', '/user/wallet/transactions', 'Get Wallet Transactions Ledger', 'Wallet', true],
    ['POST', '/shop/packages/{packageId}/purchase', 'Start Coin Package Checkout', 'Shop', true],
    ['GET', '/user/features', 'Get Feature Entitlements', 'Shop', true],
    ['GET', '/user/features/entitlements', 'Get Feature Flags', 'Shop', true],
    ['POST', '/user/features/ad-free/purchase', 'Purchase Ad-Free Entitlement', 'Shop', true],
    ['GET', '/user/unlocks/series', 'List Unlocked Series', 'Wallet', true],
    ['GET', '/user/unlocks/chapters', 'List Unlocked Chapters', 'Wallet', true],
    ['POST', '/content/{type}/{slug}/unlock', 'Unlock Series with Coins', 'Wallet', true],
    ['POST', '/chapter/{chapterId}/unlock', 'Unlock Chapter with Coins', 'Wallet', true],
    ['GET', '/user/notifications', 'Get User Notifications', 'User', true],
    ['POST', '/user/notifications/read', 'Mark Notifications Read', 'User', true],
    ['DELETE', '/user/notifications/{notificationId}', 'Delete Notification', 'User', true],

    // 8. Media
    ['GET', '/media/public/{filename}', 'Stream Public Media Asset', 'Media', false],
    ['GET', '/media/chapter/{token}', 'Stream Protected Chapter Media Page', 'Media', false],

    // 9. Admin
    ['POST', '/admin/auth/reauth', 'Admin Reauthenticate Critical Actions', 'Admin', true],
    ['GET', '/admin/overview', 'Admin Dashboard Metrics Overview', 'Admin', true],
    ['GET', '/admin/series', 'Admin List Series', 'Admin', true],
    ['GET', '/admin/contents', 'Admin List Content Alias', 'Admin', true],
    ['GET', '/admin/content', 'Admin List Content Alias', 'Admin', true],
    ['POST', '/admin/content', 'Admin Create Content Series', 'Admin', true],
    ['PUT', '/admin/content/{id}', 'Admin Update Content Series', 'Admin', true],
    ['POST', '/admin/content/{id}/lifecycle', 'Admin Change Content Lifecycle', 'Admin', true],
    ['GET', '/admin/content/{id}/preview', 'Admin Preview Content', 'Admin', true],
    ['GET', '/admin/content/{id}/revisions', 'Admin List Content Revisions', 'Admin', true],
    ['PUT', '/admin/contents/{id}/taxonomy', 'Admin Update Series Taxonomy', 'Admin', true],
    ['GET', '/admin/content/{id}/chapters', 'Admin List Series Chapters', 'Admin', true],
    ['POST', '/admin/content/{id}/chapters', 'Admin Create Chapter by Content ID', 'Admin', true],
    ['POST', '/admin/content/{type}/{slug}/chapters', 'Admin Create Chapter by Type Slug', 'Admin', true],
    ['GET', '/admin/chapters/{id}', 'Admin Get Chapter', 'Admin', true],
    ['PUT', '/admin/chapters/{id}', 'Admin Update Chapter', 'Admin', true],
    ['DELETE', '/admin/chapters/{id}', 'Admin Delete Chapter', 'Admin', true],
    ['GET', '/admin/genres', 'Admin List Genres', 'Admin', true],
    ['POST', '/admin/series_genres', 'Admin Create Genre', 'Admin', true],
    ['GET', '/admin/tags', 'Admin List Tags', 'Admin', true],
    ['POST', '/admin/series_tags', 'Admin Create Tag', 'Admin', true],
    ['GET', '/admin/users', 'Admin List Users', 'Admin', true],
    ['PUT', '/admin/users/{id}', 'Admin Update User Record', 'Admin', true],
    ['GET', '/admin/users/options', 'Admin User Select Options', 'Admin', true],
    ['GET', '/admin/rbac/roles', 'Admin List RBAC Roles', 'Admin', true],
    ['GET', '/admin/rbac/assignments', 'Admin List Role Assignments', 'Admin', true],
    ['POST', '/admin/rbac/permissions/assign', 'Admin Assign Permission to Role', 'Admin', true],
    ['DELETE', '/admin/rbac/permissions', 'Admin Revoke Permission from Role', 'Admin', true],
    ['GET', '/admin/rbac/matrix', 'Admin Get Editable Permission Matrix', 'Admin', true],
    ['GET', '/admin/blogs', 'Admin List Blogs', 'Admin', true],
    ['GET', '/admin/blogs/pending', 'Admin List Pending Blogs', 'Admin', true],
    ['POST', '/admin/blogs/{id}/approve', 'Admin Approve Blog Post', 'Admin', true],
    ['POST', '/admin/blogs/{id}/hide', 'Admin Hide Blog Post', 'Admin', true],
    ['DELETE', '/admin/blogs/{id}', 'Admin Delete Blog Post', 'Admin', true],
    ['GET', '/admin/comments', 'Admin List Comments', 'Admin', true],
    ['DELETE', '/admin/comments/{id}', 'Admin Delete Comment', 'Admin', true],
    ['GET', '/admin/uploads', 'Admin List System Uploads', 'Admin', true],
    ['DELETE', '/admin/uploads/{id}', 'Admin Delete System Upload', 'Admin', true],
    ['POST', '/admin/uploads/bulk-delete', 'Admin Bulk Delete System Uploads', 'Admin', true],
    ['POST', '/admin/uploads/{id}/optimize', 'Admin Optimize System Upload', 'Admin', true],
    ['POST', '/admin/upload-images', 'Admin Bulk Upload Media Files', 'Admin', true],
    ['GET', '/admin/shop/packages', 'Admin List Shop Packages', 'Admin', true],
    ['POST', '/admin/shop/packages', 'Admin Create Shop Package', 'Admin', true],
    ['PUT', '/admin/shop/packages/{id}', 'Admin Update Shop Package', 'Admin', true],
    ['GET', '/admin/features', 'Admin List Premium Feature Products', 'Admin', true],
    ['PUT', '/admin/features/ad-free', 'Admin Configure Ad-Free Feature', 'Admin', true],
    ['GET', '/admin/wallets/{userId}', 'Admin Get User Wallet Summary', 'Admin', true],
    ['GET', '/admin/wallets/{userId}/transactions', 'Admin Get User Wallet Ledger', 'Admin', true],
    ['POST', '/admin/wallets/{userId}/grant-package', 'Admin Grant Coin Package to User', 'Admin', true],
    ['POST', '/admin/wallets/{userId}/credit', 'Admin Credit User Coins', 'Admin', true],
    ['POST', '/admin/wallets/{userId}/debit', 'Admin Debit User Coins', 'Admin', true],
    ['PUT', '/admin/series/{id}/pricing', 'Admin Update Series Pricing Strategy', 'Admin', true],
    ['PUT', '/admin/chapters/{id}/pricing', 'Admin Update Chapter Unlock Price', 'Admin', true],
    ['GET', '/admin/queue/jobs', 'Admin List Background Queue Jobs', 'Admin', true],
    ['GET', '/admin/system/health', 'Admin Get System Health', 'Admin', true],
    ['POST', '/admin/queue/jobs/{id}/retry', 'Admin Retry Queue Job', 'Admin', true],
    ['POST', '/admin/queue/jobs/{id}/cancel', 'Admin Cancel Queue Job', 'Admin', true],
    ['POST', '/admin/queue/run-once', 'Admin Execute Queue Worker Step', 'Admin', true],
    ['POST', '/admin/retention/cleanup', 'Admin Trigger Retention Cleanup', 'Admin', true],
    ['POST', '/admin/maintenance/backup', 'Admin Trigger Database Backup', 'Admin', true],
    ['POST', '/admin/maintenance/sitemap', 'Admin Trigger Sitemap Generation', 'Admin', true],
    ['POST', '/admin/maintenance/warmup', 'Admin Trigger Cache Warmup', 'Admin', true],
    ['POST', '/admin/maintenance/analytics', 'Admin Trigger Analytics Rollup', 'Admin', true],
    ['POST', '/admin/maintenance/api-tests', 'Admin Trigger API Contract Tests', 'Admin', true],
    ['POST', '/admin/maintenance/openapi', 'Admin Regenerate OpenAPI Contract', 'Admin', true],
    ['POST', '/admin/maintenance/seed-data', 'Admin Seed Default Data', 'Admin', true],
    ['GET', '/admin/maintenance/env', 'Admin Get Environment Config', 'Admin', true],
    ['POST', '/admin/maintenance/env', 'Admin Save Environment Config', 'Admin', true],
    ['GET', '/admin/audit-logs', 'Admin Get Audit Activity Logs', 'Admin', true],
    ['GET', '/admin/login-events', 'Admin Get Security Login Events', 'Admin', true],
    ['GET', '/admin/moderation-actions', 'Admin Get Moderation Actions', 'Admin', true],
    ['POST', '/admin/moderation-actions', 'Admin Create Moderation Record', 'Admin', true],
    ['GET', '/admin/logs/access', 'Admin View Web Access Logs', 'Admin', true],
    ['GET', '/admin/logs/error', 'Admin View System Error Logs', 'Admin', true],
    ['GET', '/admin/stats/visits', 'Admin Analytics Visitor Stats', 'Admin', true],
    ['GET', '/admin/stats/views', 'Admin Analytics Content Views', 'Admin', true],
    ['GET', '/admin/stats/blogs', 'Admin Analytics Blog Engagement', 'Admin', true],
    ['GET', '/admin/stats/reputation', 'Admin Analytics User Reputation', 'Admin', true],
    ['GET', '/admin/metrics', 'Admin System Metrics Snapshot', 'Admin', true],
    ['GET', '/admin/dashboard', 'Admin System Metrics Alias', 'Admin', true],
    ['GET', '/admin/metrics/insights', 'Admin Performance Insights', 'Admin', true],
    ['POST', '/admin/chapters/bulk', 'Admin Bulk Update Chapters', 'Admin', true],
    ['GET', '/admin/series/{id}/team', 'Admin List Series Team', 'Admin', true],
    ['POST', '/admin/series/{id}/team', 'Admin Assign Series Team Member', 'Admin', true],
    ['DELETE', '/admin/series/team/{assignmentId}', 'Admin Remove Series Team Member', 'Admin', true],
    ['GET', '/admin/config/site', 'Admin Get Site Configuration', 'Admin', true],
    ['POST', '/admin/config/site', 'Admin Update Site Configuration', 'Admin', true],
    ['GET', '/admin/webhooks', 'Admin List Webhooks', 'Admin', true],
    ['POST', '/admin/webhooks', 'Admin Create Webhook', 'Admin', true],
    ['PUT', '/admin/webhooks/{id}', 'Admin Update Webhook', 'Admin', true],
    ['DELETE', '/admin/webhooks/{id}', 'Admin Delete Webhook', 'Admin', true],
    ['POST', '/admin/webhooks/{id}/test', 'Admin Test Webhook', 'Admin', true],
    ['GET', '/admin/reports', 'Admin List Reports', 'Admin', true],
    ['GET', '/admin/reports/{id}', 'Admin Get Report', 'Admin', true],
    ['PATCH', '/admin/reports/{id}', 'Admin Update Report', 'Admin', true],
    ['PUT', '/admin/reports/{id}', 'Admin Replace Report Status', 'Admin', true],
    ['GET', '/admin/analytics/monetization', 'Admin Get Monetization Analytics', 'Admin', true],
    ['GET', '/admin/finance/transactions', 'Admin List Finance Transactions', 'Admin', true],
    ['POST', '/admin/finance/transactions/{id}/refund', 'Admin Refund Finance Transaction', 'Admin', true],
    ['GET', '/admin/analytics/search-insights', 'Admin Get Search Insights', 'Admin', true],
    ['GET', '/admin/analytics/funnel/{id}', 'Admin Get Series Reading Funnel', 'Admin', true],
    ['PUT', '/admin/taxonomies/{id}', 'Admin Rename Taxonomy Item', 'Admin', true],
    ['DELETE', '/admin/taxonomies/{id}', 'Admin Delete Unused Taxonomy Item', 'Admin', true],
    ['POST', '/admin/taxonomies/merge', 'Admin Merge Taxonomy Items', 'Admin', true],
    ['PUT', '/admin/taxonomies/order', 'Admin Reorder Taxonomy Items', 'Admin', true],
];

$paths = [];

foreach ($endpoints as [$method, $uri, $summary, $tag, $secured]) {
    $methodLower = strtolower($method);
    $pathItem = [
        'summary' => $summary,
        'tags' => [$tag],
        'responses' => [
            '200' => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ApiResponseEnvelope'
                        ]
                    ]
                ]
            ],
            '400' => [
                'description' => 'Validation or Client error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ApiResponseEnvelope'
                        ]
                    ]
                ]
            ]
        ]
    ];

    if ($secured) {
        $pathItem['security'] = [
            ['cookieAuth' => []],
            ['bearerAuth' => []]
        ];
        $pathItem['responses']['401'] = [
            'description' => 'Unauthorized - Authentication required'
        ];
        $pathItem['responses']['403'] = [
            'description' => 'Forbidden - Insufficient permissions'
        ];
    }

    // Extract path parameters
    if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches)) {
        $pathItem['parameters'] = [];
        foreach ($matches[1] as $param) {
            $pathItem['parameters'][] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string']
            ];
        }
    }

    if (!isset($paths[$uri])) {
        $paths[$uri] = [];
    }
    $paths[$uri][$methodLower] = $pathItem;
}

$openapi = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'NM-Reader API',
        'version' => '1.0.0',
        'description' => 'Canonical REST API specification for NM-Reader P2 Freeze and React CSR Client Integration.'
    ],
    'servers' => [
        [
            'url' => '/api/v1',
            'description' => 'Primary API Server v1'
        ]
    ],
    'components' => [
        'securitySchemes' => [
            'cookieAuth' => [
                'type' => 'apiKey',
                'in' => 'cookie',
                'name' => 'nm_reader_session',
                'description' => 'Session cookie for browser-based authentication'
            ],
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Bearer token for API authorization'
            ],
            'csrfToken' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-CSRF-Token',
                'description' => 'CSRF token required for state-changing mutations'
            ]
        ],
        'schemas' => [
            'ApiResponseEnvelope' => [
                'type' => 'object',
                'required' => ['status', 'data', 'meta', 'error'],
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['success', 'error']],
                    'data' => ['description' => 'Payload object or array, null on error'],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'pagination' => ['$ref' => '#/components/schemas/PaginationMeta'],
                            'page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer']
                        ]
                    ],
                    'error' => [
                        'nullable' => true,
                        'oneOf' => [
                            ['type' => 'null'],
                            ['$ref' => '#/components/schemas/ApiError']
                        ]
                    ]
                ]
            ],
            'ApiError' => [
                'type' => 'object',
                'required' => ['code', 'key', 'message', 'params'],
                'properties' => [
                    'code' => ['type' => 'integer', 'example' => 400],
                    'key' => ['type' => 'string', 'example' => 'VALIDATION_FAILED'],
                    'message' => ['type' => 'string', 'example' => 'Validation failed'],
                    'params' => ['type' => 'array', 'items' => new stdClass()]
                ]
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'required' => ['type', 'per_page'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['offset', 'cursor']],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer', 'nullable' => true],
                    'total_pages' => ['type' => 'integer', 'nullable' => true],
                    'next_cursor' => ['type' => 'string', 'nullable' => true],
                    'has_more' => ['type' => 'boolean', 'nullable' => true]
                ]
            ]
        ]
    ],
    'paths' => $paths
];

$targetFile = $basePath . '/docs/api/openapi.json';
file_put_contents($targetFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "SUCCESS: openapi.json generated with " . count($paths) . " routes at {$targetFile}\n";
