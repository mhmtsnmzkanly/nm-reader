-- Full-text indexes for search.

ALTER TABLE series
  ADD FULLTEXT INDEX ft_series_search (title, slug, description);

ALTER TABLE series_metadata
  ADD FULLTEXT INDEX ft_series_meta_search (author, artist, alternative_titles);
