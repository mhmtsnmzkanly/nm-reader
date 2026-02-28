<div class="reader-container mx-auto max-w-1400">
  <div class="card reader-toolbar mb-4 flex items-center justify-between p-2">
    <div class="flex items-center gap-2">
      <button id="prevChapterBtn" class="btn btn-sm btn-outline" onclick="Reader.prevChapter()">&laquo; Prev</button>
      <select class="form-item py-1 px-3 w-180" id="chapterSelect">
        <?php if (isset($ssr_chapter)): ?>
          <option value="<?= $ssr_chapter[
              "chapter_number"
          ] ?>" selected>Chapter <?= $ssr_chapter["chapter_number"] ?></option>
        <?php endif; ?>
      </select>
      <button id="nextChapterBtn" class="btn btn-sm btn-outline" onclick="Reader.nextChapter()">Next &raquo;</button>
    </div>
    <button class="btn btn-sm btn-outline" id="openReaderSettings">⚙️ Settings</button>
  </div>

  <div class="reader-main">
    <div id="mangaView" class="card p-0 overflow-hidden hidden border-0 shadow-none bg-transparent unselectable">
      <div class="manga-pages"></div>
    </div>
    <div id="novelView" class="card unselectable <?= isset($ssr_chapter) &&
    $ssr_chapter["type"] === "text"
        ? ""
        : "hidden" ?> fs-1-2 lh-2">
      <div class="novel-content markdown-body p-4 p-md-5">
        <?php if (isset($ssr_chapter) && $ssr_chapter["type"] === "text"): ?>
          <?= $ssr_chapter["body"]
            // Note: Assuming body is already sanitized or will be parsed by JS hydration
            ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="reader-comments mt-5 max-w-900 mx-auto">
    <div class="card">
      <div class="card-header border-bottom bg-surface p-3 m-0">
        <h3 class="m-0">Chapter Comments</h3>
      </div>

      <div class="p-4">
        <form id="readerCommentForm" class="mb-4">
          <div class="flex flex-col gap-3 mb-3">
            <textarea id="readerCommentInput" class="form-item" placeholder="Write a comment (Markdown supported)..." rows="4"></textarea>
            <div class="text-xs text-muted font-bold uppercase tracking-wider">Preview</div>
            <div id="commentPreview" class="form-item bg-surface overflow-auto markdown-body p-3 min-h-80 border-dashed">
              <span class="text-muted italic">Preview will appear here...</span>
            </div>
          </div>
          <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Post Comment</button>
          </div>
        </form>

        <div id="readerCommentsList" class="flex flex-col gap-4">
          <div class="text-center py-3 text-muted">Loading comments...</div>
        </div>
      </div>
    </div>
  </div>
</div>
