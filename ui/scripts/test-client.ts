/**
 * Client Unit Tests for Novel Markdown Rendering
 * Verifies TextReader, ReactMarkdown integration, supported syntax, security, and edge cases.
 */

import React from 'react';
import ReactDOMServer from 'react-dom/server';
import { TextReader } from '../src/components/reader/TextReader';
import { Chapter } from '../src/types/api';
import { mockChapters } from '../src/mocks/fixtures';

let passedTests = 0;
let totalTests = 0;

function assert(condition: boolean, testName: string, details?: string) {
  totalTests++;
  if (condition) {
    console.log(`  ✓ PASS: ${testName}`);
    passedTests++;
  } else {
    console.error(`  ✗ FAIL: ${testName}`);
    if (details) {
      console.error(`    Details: ${details}`);
    }
    throw new Error(`Test failed: ${testName}`);
  }
}

function renderTextReader(chapter: Chapter, readerSettings?: any, settings?: any): string {
  return ReactDOMServer.renderToStaticMarkup(
    React.createElement(TextReader, {
      chapter,
      readerSettings,
      settings,
    })
  );
}

function runTests() {
  console.log('\n=============================================');
  console.log(' RUNNING NOVEL MARKDOWN RENDERING TESTS');
  console.log('=============================================\n');

  // Test 1: Empty and Edge Cases
  console.log('--- Suite 1: Empty & Edge Cases ---');
  {
    const emptyChapter: Chapter = {
      id: 'ch_empty',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Empty Chapter',
      type: 'text',
      created_at: new Date().toISOString(),
      body: '',
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const emptyHtml = renderTextReader(emptyChapter);
    assert(
      emptyHtml.includes('id="reader-no-text-content"'),
      'Empty body renders fallback without crashing'
    );

    const nullChapter: Chapter = {
      ...emptyChapter,
      body: null as any,
    };
    const nullHtml = renderTextReader(nullChapter);
    assert(
      nullHtml.includes('id="reader-no-text-content"'),
      'Null body renders fallback without crashing'
    );

    const whitespaceChapter: Chapter = {
      ...emptyChapter,
      body: '   \n\n  \t ',
    };
    const whitespaceHtml = renderTextReader(whitespaceChapter);
    assert(
      whitespaceHtml.includes('id="reader-no-text-content"'),
      'Whitespace-only body renders fallback without crashing'
    );
  }

  // Test 2: Plain Text without Markdown formatting
  console.log('\n--- Suite 2: Plain Text Rendering ---');
  {
    const plainChapter: Chapter = {
      id: 'ch_plain',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Düz Metin Bölümü',
      type: 'text',
      created_at: new Date().toISOString(),
      body: 'Bu sade bir romandır.\n\nİkinci paragrafta herhangi bir markdown işareti yoktur.',
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const plainHtml = renderTextReader(plainChapter);
    assert(
      plainHtml.includes('Bu sade bir romandır.') &&
      plainHtml.includes('İkinci paragrafta herhangi bir markdown işareti yoktur.') &&
      plainHtml.includes('<p'),
      'Plain text is rendered as valid paragraphs'
    );
  }

  // Test 3: Markdown Headings (H1, H2, H3, H4)
  console.log('\n--- Suite 3: Headings Rendering ---');
  {
    const headingChapter: Chapter = {
      id: 'ch_headings',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Başlıklar',
      type: 'text',
      created_at: new Date().toISOString(),
      body: '# Ana Başlık\n\n## Alt Başlık\n\n### Üçüncü Seviye Başlık\n\n#### Dördüncü Başlık',
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(headingChapter);
    assert(html.includes('<h1') && html.includes('Ana Başlık'), 'H1 heading is rendered');
    assert(html.includes('<h2') && html.includes('Alt Başlık'), 'H2 heading is rendered');
    assert(html.includes('<h3') && html.includes('Üçüncü Seviye Başlık'), 'H3 heading is rendered');
    assert(html.includes('<h4') && html.includes('Dördüncü Başlık'), 'H4 heading is rendered');
  }

  // Test 4: Formatting (Bold, Italic, Bold+Italic, Strikethrough)
  console.log('\n--- Suite 4: Text Formatting ---');
  {
    const formatChapter: Chapter = {
      id: 'ch_format',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Biçimlendirmeler',
      type: 'text',
      created_at: new Date().toISOString(),
      body: '**kalın metin** ve *italik metin* ile ***kalın ve italik*** ayrıca ~~üstü çizili metin~~.',
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(formatChapter);
    assert(html.includes('<strong') && html.includes('kalın metin'), 'Bold text renders as strong');
    assert(html.includes('<em') && html.includes('italik metin'), 'Italic text renders as em');
    assert(
      html.includes('kalın ve italik') && (html.includes('<em') || html.includes('<strong')),
      'Bold+Italic text renders formatted'
    );
    assert(
      html.includes('<del') && html.includes('üstü çizili metin'),
      'Strikethrough text renders as del'
    );
  }

  // Test 5: Lists (Unordered, Ordered, Nested)
  console.log('\n--- Suite 5: Lists Rendering ---');
  {
    const listChapter: Chapter = {
      id: 'ch_lists',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Listeler',
      type: 'text',
      created_at: new Date().toISOString(),
      body: `- Madde 1
- Madde 2
  - Alt Madde 2.1
  - Alt Madde 2.2
- Madde 3

1. Birinci Adım
2. İkinci Adım
3. Üçüncü Adım`,
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(listChapter);
    assert(html.includes('<ul') && html.includes('<li') && html.includes('Madde 1'), 'Unordered list renders');
    assert(html.includes('<ol') && html.includes('Birinci Adım'), 'Ordered list renders');
    assert(html.includes('Alt Madde 2.1'), 'Nested list items render');
  }

  // Test 6: Blockquotes (Single & Nested)
  console.log('\n--- Suite 6: Blockquotes Rendering ---');
  {
    const bqChapter: Chapter = {
      id: 'ch_bq',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Alıntılar',
      type: 'text',
      created_at: new Date().toISOString(),
      body: `> "Bu kadim şehir bizi asla hatırlamayacak."
>
> > "Gölgeler derinleştiğinde, ışığı arayanlar bile karanlığa boyun eğer."`,
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(bqChapter);
    assert(html.includes('<blockquote'), 'Blockquote element is rendered');
    assert(
      html.includes('Bu kadim şehir bizi asla hatırlamayacak.') &&
      html.includes('Gölgeler derinleştiğinde'),
      'Nested blockquote content is present'
    );
  }

  // Test 7: Links & Security (rel, target)
  console.log('\n--- Suite 7: Links & Security Attributes ---');
  {
    const linkChapter: Chapter = {
      id: 'ch_link',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Bağlantılar',
      type: 'text',
      created_at: new Date().toISOString(),
      body: '[Eski Krallık Arşivi](https://example.com/archive) ve [İç Bölüm](/novel/chapter/2)',
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(linkChapter);
    assert(html.includes('<a href="https://example.com/archive"'), 'External link has correct href');
    assert(html.includes('target="_blank"'), 'External link has target="_blank"');
    assert(html.includes('rel="noopener noreferrer"'), 'External link has rel="noopener noreferrer"');
    assert(html.includes('Eski Krallık Arşivi'), 'Link anchor text is rendered');
  }

  // Test 8: Code Blocks & Inline Code
  console.log('\n--- Suite 8: Inline Code & Code Blocks ---');
  {
    const codeChapter: Chapter = {
      id: 'ch_code',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Kod Blokları',
      type: 'text',
      created_at: new Date().toISOString(),
      body: `Büyü \`Aether Gate\` ile çalışır.

\`\`\`text
KAYIT 17-B
Kuzey kapısı açılmamalı.
Saat 03:17:42.
\`\`\`

---`,
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(codeChapter);
    assert(html.includes('<code') && html.includes('Aether Gate'), 'Inline code is rendered');
    assert(html.includes('<pre') && html.includes('KAYIT 17-B'), 'Code block inside pre is rendered');
    assert(html.includes('<hr'), 'Horizontal rule hr is rendered');
  }

  // Test 9: Raw HTML Protection (Security against XSS and raw HTML tags)
  console.log('\n--- Suite 9: Raw HTML Protection ---');
  {
    const xssChapter: Chapter = {
      id: 'ch_xss',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Güvenlik Testi',
      type: 'text',
      created_at: new Date().toISOString(),
      body: `Zararlı içerik testi: <script>alert('xss')</script> ve <div class="evil">Raw div</div> ve <img src="x" onerror="alert(1)">`,
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(xssChapter);
    assert(!html.includes('<script>'), 'Raw <script> tags are NOT rendered unescaped in DOM');
    assert(!html.includes('<div class="evil">'), 'Raw <div> tags are NOT rendered as raw DOM nodes');
    assert(!html.includes('onerror="alert(1)"'), 'Raw HTML attributes are NOT executed as active DOM attributes');
  }

  // Test 10: Turkish Characters and Markdown Escaping
  console.log('\n--- Suite 10: Turkish Characters & Escaping ---');
  {
    const trChapter: Chapter = {
      id: 'ch_tr',
      content_id: 'novel_01',
      series: { id: 's1', title: 'Novel', slug: 'novel', type: 'novel' },
      chapter_number: '1',
      title: 'Türkçe Karakterler',
      type: 'text',
      created_at: new Date().toISOString(),
      body: `Türkçe karakterler: ğ ü ş ö ç İ ı Ğ Ü Ş Ö Ç.

Kaçış örneği: \\*bu yıldızlar italik değildir\\* ve \\[bu parantezler link değildir\\].`,
      pages: [],
      navigation: { previous: null, next: null },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const html = renderTextReader(trChapter);
    assert(
      html.includes('ğ ü ş ö ç İ ı Ğ Ü Ş Ö Ç'),
      'Turkish characters render intact'
    );
    assert(
      html.includes('*bu yıldızlar italik değildir*') && !html.includes('<em>bu yıldızlar italik değildir</em>'),
      'Escaped markdown asterisks do not trigger italic rendering'
    );
  }

  // Test 11: Real Mock Novel Chapter in Fixtures
  console.log('\n--- Suite 11: Mock Novel Chapter Integration ---');
  {
    const shadowSlaveChapters = mockChapters['shadow-slave-chronicles'];
    assert(Array.isArray(shadowSlaveChapters) && shadowSlaveChapters.length > 0, 'Shadow Slave mock chapters exist');

    const ch12 = shadowSlaveChapters.find((c) => c.chapter_number === '12');
    assert(!!ch12, 'Chapter 12 exists in mock data');
    assert(typeof ch12?.body === 'string' && ch12.body.length > 500, 'Chapter 12 has rich novel markdown body');

    const mockReaderChapter: Chapter = {
      id: ch12!.id,
      content_id: ch12!.content_id,
      series: { id: 'f6g7h8', title: 'Shadow Slave Chronicles', slug: 'shadow-slave-chronicles', type: 'web-novel' },
      chapter_number: '12',
      title: ch12!.title,
      type: 'text',
      created_at: ch12!.created_at || new Date().toISOString(),
      body: ch12!.body,
      pages: [],
      navigation: { previous: '11', next: '13' },
      access: { granted: true, locked: false, price_coin: 0 },
    };

    const fullHtml = renderTextReader(mockReaderChapter);
    assert(fullHtml.includes('id="novel-text-reader"'), 'TextReader renders novel article');
    assert(fullHtml.includes('Bölüm XII — Küllerin Altındaki Şehir'), 'Chapter heading renders');
    assert(fullHtml.includes('Kuzey Kapısı ve Unutulmuş Geçit'), 'H2 subhead renders');
    assert(fullHtml.includes('Kadim Yazıtlar ve Gizli Hazırlık'), 'H3 subhead renders');
    assert(fullHtml.includes('Keşif Heyeti ve Durum Tablosu'), 'H3 table heading renders');
    assert(fullHtml.includes('<table') && fullHtml.includes('<th') && fullHtml.includes('<td'), 'GFM table elements are rendered');
    assert(fullHtml.includes('Gölge Gezgini') && fullHtml.includes('Işık Muhafızı'), 'Table cell data renders accurately');
    assert(fullHtml.includes('Eski Krallık Arşivi'), 'Link renders');
    assert(fullHtml.includes('SİSTEM KAYDI 17-B') || fullHtml.includes('KAYIT 17-B'), 'Code block renders');
    assert(fullHtml.includes('Aether Gate'), 'Inline code renders');
    assert(fullHtml.includes('Muhafız Valerius'), 'Blockquote quote renders');
  }

  console.log('\n=============================================');
  console.log(` ALL TESTS COMPLETED: ${passedTests}/${totalTests} PASSED`);
  console.log('=============================================\n');
}

runTests();
