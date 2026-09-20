<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\OutputSanitizer;
use App\Repositories\BlogRepository;
use App\Services\I18nService;
use App\Services\SiteConfigService;
use App\Services\WebPageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Public blog listing and detail pages. */
final class BlogPageController extends BasePageController
{
    public function __construct(
        WebPageRenderer $renderer,
        I18nService $i18n,
        SiteConfigService $siteConfig,
        private readonly BlogRepository $blogRepository,
    ) {
        parent::__construct($renderer, $i18n, $siteConfig);
    }

    public function blog(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $slug = (string) ($args['slug'] ?? '');
        $siteName = $this->siteConfig->siteName();
        $seo = [
            'title' => 'Blog - ' . $siteName,
            'description' => $siteName . ' topluluğundan manga, manhwa, webtoon ve novel yazıları, rehberleri ve analizleri.',
            'type' => 'website', 'robots' => 'index,follow',
        ];
        $post = null;
        if ($slug !== '') {
            $post = $this->blogRepository->findApprovedBySlug($slug);
            if ($post === null) return $this->notFound($request, $response);
            $postTitle = (string) ($post['title'] ?? 'Blog');
            $postDescription = $this->truncateDescription((string) ($post['body'] ?? ''));
            $seo['title'] = $postTitle . ' - Blog';
            $seo['description'] = $postDescription !== '' ? $postDescription : 'Blog yazisi';
            $seo['type'] = 'article';
            $seo['canonical'] = $this->absoluteUrl($request, '/blogs/' . $slug);
            $seo['image'] = !empty($post['cover_image']) ? $post['cover_image'] : $this->siteConfig->defaultContentCoverImage();
            $schemaImage = $this->absoluteUrl($request, (string) $seo['image']);
            $seo['json_ld'] = [
                '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $postTitle,
                'image' => $schemaImage,
                'author' => ['@type' => 'Person', 'name' => (string) ($post['author_username'] ?? 'NMR Author')],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $siteName,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $this->absoluteUrl($request, $this->siteConfig->siteLogo()),
                    ],
                ],
                'datePublished' => (string) ($post['approved_at'] ?? ($post['created_at'] ?? gmdate('Y-m-d H:i:s'))),
                'dateModified' => (string) ($post['updated_at'] ?? ($post['approved_at'] ?? $post['created_at'] ?? '')),
                'url' => $this->absoluteUrl($request, '/blogs/' . $slug), 'description' => $seo['description'],
                'mainEntityOfPage' => $this->absoluteUrl($request, '/blogs/' . $slug),
                'inLanguage' => 'tr-TR',
            ];
        }
        $blogBootstrap = null;
        if (is_array($post)) {
            $blogBootstrap = OutputSanitizer::sanitizeFields($post, [
                'title', 'body', 'author_username', 'approver_username', 'cover_image', 'status', 'excerpt',
            ]);
            $blogBootstrap = array_intersect_key($blogBootstrap, array_flip([
                'id', 'title', 'slug', 'body', 'cover_image', 'created_at', 'approved_at', 'author_username',
                'excerpt', 'likes', 'upvote_count', 'downvote_count', 'comments_count', 'status',
            ]));
        }
        $currentPage = $blogBootstrap === null
            ? ['route' => 'blogs', 'data' => ['items' => $this->blogRepository->listApproved(1, 20)]]
            : ['route' => 'blog', 'data' => ['slug' => $slug, 'blog' => $blogBootstrap]];
        return $this->render($request, $response, [
            'breadcrumbs' => $this->breadcrumbs($request, 'blog', [
                'title' => ($slug !== '' && $post) ? ($post['title'] ?? '') : '',
            ]),
            'current_page' => $currentPage,
        ], $seo['title'], $seo);
    }
}
