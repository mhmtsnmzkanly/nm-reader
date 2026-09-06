# Web page controller architecture

Web routes are grouped by responsibility:

- `ContentPageController`: home, browse/listing, search, taxonomy, content and chapter routes.
- `BlogPageController`: blog listing and detail routes.
- `AccountPageController`: login/register and profile routes.
- `AdminShellController`: the `/panel` Lime CSR shell.
- `SystemPageController`: robots, sitemap, i18n JSON, frontend error logging and the error shell.

`BasePageController` and `WebPageRenderer` contain only shared page helpers and the React
shell/SEO pipeline. Page-specific logic belongs in the corresponding controller; shared startup
context must be produced by `WebContextBuilder`, and canonical/asset URL handling by
`WebUrlService`. There is no longer a `WebController` route dependency.
