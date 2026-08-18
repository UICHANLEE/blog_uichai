#!/usr/bin/env node

import { cp, mkdir, mkdtemp, rename, rm, writeFile } from 'node:fs/promises';
import { dirname, extname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(scriptDirectory, '..');
const snapshotDirectory = join(projectRoot, 'static-snapshot');
const sourceOrigin = (process.env.WORDPRESS_EXPORT_ORIGIN || 'http://127.0.0.1:8090').replace(/\/$/, '');
const canonicalPlaceholder = 'https://odd-note.invalid';
const maximumPages = 500;

const sourceUrl = new URL(sourceOrigin);
if (!['http:', 'https:'].includes(sourceUrl.protocol)) {
  throw new Error('WORDPRESS_EXPORT_ORIGIN은 http 또는 https 주소여야 합니다.');
}

const temporaryDirectory = await mkdtemp(join(projectRoot, '.static-snapshot-'));
let completed = false;

function decodeHtml(value) {
  return value.replaceAll('&#038;', '&').replaceAll('&amp;', '&');
}

function routeFile(pathname) {
  if (pathname === '/') {
    return 'index.html';
  }

  const cleanPath = decodeURIComponent(pathname).replace(/^\/+|\/+$/g, '');
  if (cleanPath.split('/').some((segment) => segment === '..')) {
    throw new Error(`안전하지 않은 공개 경로입니다: ${pathname}`);
  }
  return join(cleanPath, 'index.html');
}

function isIndexable(pathname) {
  return !pathname.startsWith('/tag/') && !pathname.startsWith('/author/');
}

function shouldCrawl(candidate) {
  if (candidate.origin !== sourceUrl.origin || candidate.search) {
    return false;
  }

  const blockedPrefixes = [
    '/wp-admin',
    '/wp-login.php',
    '/wp-json',
    '/xmlrpc.php',
    '/wp-comments-post.php',
    '/feed',
    '/comments/feed',
  ];

  if (blockedPrefixes.some((prefix) => candidate.pathname.startsWith(prefix))) {
    return false;
  }

  const extension = extname(candidate.pathname).toLowerCase();
  return !extension || extension === '.html';
}

async function fetchResponse(url, acceptedStatuses = [200]) {
  const response = await fetch(url, {
    headers: { 'user-agent': 'OddNoteStaticExporter/1.0' },
    signal: AbortSignal.timeout(20_000),
  });

  if (!acceptedStatuses.includes(response.status)) {
    throw new Error(`${url} 응답 상태가 ${response.status}입니다.`);
  }

  return response;
}

async function fetchCollection(resource) {
  const records = [];
  let page = 1;
  let totalPages = 1;

  do {
    const endpoint = new URL(`/wp-json/wp/v2/${resource}`, sourceUrl);
    endpoint.searchParams.set('per_page', '100');
    endpoint.searchParams.set('page', String(page));
    endpoint.searchParams.set('_fields', 'link,slug,status,count');

    const response = await fetchResponse(endpoint);
    records.push(...await response.json());
    totalPages = Number(response.headers.get('x-wp-totalpages') || 1);
    page += 1;
  } while (page <= totalPages);

  return records;
}

function extractPageLinks(html) {
  const links = [];

  for (const match of html.matchAll(/href=["']([^"']+)["']/gi)) {
    const value = decodeHtml(match[1]);
    if (!value || value.startsWith('#') || value.startsWith('mailto:') || value.startsWith('tel:')) {
      continue;
    }

    try {
      const candidate = new URL(value, sourceUrl);
      candidate.hash = '';
      if (shouldCrawl(candidate)) {
        links.push(candidate.href);
      }
    } catch {
      // Ignore malformed third-party links. WordPress pages remain exportable.
    }
  }

  return links;
}

function extractAssetPaths(html) {
  const values = [];

  for (const match of html.matchAll(/(?:src|href)=["']([^"']+)["']/gi)) {
    values.push(match[1]);
  }

  for (const match of html.matchAll(/srcset=["']([^"']+)["']/gi)) {
    for (const candidate of match[1].split(',')) {
      values.push(candidate.trim().split(/\s+/)[0]);
    }
  }

  const assets = [];
  const allowedExtensions = new Set([
    '.avif', '.css', '.gif', '.ico', '.jpeg', '.jpg', '.js', '.png', '.svg', '.webp', '.woff', '.woff2',
  ]);

  for (const rawValue of values) {
    try {
      const candidate = new URL(decodeHtml(rawValue), sourceUrl);
      const extension = extname(candidate.pathname).toLowerCase();
      if (
        candidate.origin === sourceUrl.origin &&
        allowedExtensions.has(extension) &&
        (candidate.pathname.startsWith('/wp-content/') || candidate.pathname.startsWith('/wp-includes/'))
      ) {
        assets.push(candidate.pathname);
      }
    } catch {
      // Ignore non-URL attribute values.
    }
  }

  return assets;
}

function removeWordPressDiscovery(html) {
  return html
    .replace(/<link\b(?=[^>]*\brel=["']alternate["'])(?=[^>]*\btype=["']application\/(?:rss|atom)\+xml["'])[^>]*>\s*/gi, '')
    .replace(/<link rel=["']alternate["'][^>]*title=["']oEmbed[^>]*>\s*/gi, '')
    .replace(/<link rel=["']https:\/\/api\.w\.org\/["'][^>]*>\s*/gi, '')
    .replace(/<link rel=["']alternate["'][^>]*type=["']application\/json["'][^>]*>\s*/gi, '')
    .replace(/<link rel=["']EditURI["'][^>]*>\s*/gi, '')
    .replace(/<link rel=["']shortlink["'][^>]*>\s*/gi, '')
    .replace(/<meta name=["']generator["'][^>]*>\s*/gi, '')
    .replace(/<style id=["']wp-emoji-styles-inline-css["'][\s\S]*?<\/style>\s*/gi, '')
    .replace(/<script type=["']speculationrules["'][\s\S]*?<\/script>\s*/gi, '')
    .replace(/<script id=["']wp-emoji-settings["'][\s\S]*?<\/script>\s*<script type=["']module["'][\s\S]*?<\/script>\s*/gi, '');
}

function sanitizeHtml(html, pathname, notFound = false) {
  let output = removeWordPressDiscovery(html);

  output = output
    .replace(/<html\s+lang=["'][^"']+["']/i, '<html lang="ko-KR"')
    .replace(/<meta\s+name=["']robots["'][^>]*>\s*/gi, '')
    .replace(/<link\s+rel=["']canonical["'][^>]*>\s*/gi, '')
    .replaceAll(sourceOrigin, '')
    .replace(/href=["']\/feed\/["']/gi, 'href="/feed.xml"')
    .replace(
      /<form role=["']search["'][\s\S]*?<\/form>/gi,
      '<a class="text-link" href="/stories/" data-cursor="BROWSE">전체 글에서 찾아보기 <span aria-hidden="true">↗</span></a>',
    );

  const pageUrl = `${canonicalPlaceholder}${pathname}`;
  const robotsMode = notFound || !isIndexable(pathname) ? 'noindex' : 'indexable';
  const headAdditions = [`<!-- ODD_NOTE_ROBOTS:${robotsMode} -->`];

  if (!notFound) {
    headAdditions.push(`<link rel="canonical" href="${pageUrl}">`);
    if (/<meta property=["']og:url["'][^>]*>/i.test(output)) {
      output = output.replace(
        /<meta property=["']og:url["'][^>]*>/i,
        `<meta property="og:url" content="${pageUrl}">`,
      );
    } else {
      headAdditions.push(`<meta property="og:url" content="${pageUrl}">`);
    }
  } else {
    output = output.replace(/<meta property=["']og:url["'][^>]*>\s*/gi, '');
  }

  output = output.replace(
    /<meta property=["']og:image["'] content=["'](\/[^"']+)["']>/i,
    (_match, imagePath) => `<meta property="og:image" content="${canonicalPlaceholder}${imagePath}">`,
  );

  output = output.replace(
    '</head>',
    `${headAdditions.join('\n')}\n<link rel="alternate" type="application/rss+xml" title="Odd Note RSS" href="/feed.xml">\n</head>`,
  );

  return output.replace(/[ \t]+$/gm, '');
}

async function writeRoute(pathname, html) {
  const relativeFile = routeFile(pathname);
  const destination = join(temporaryDirectory, relativeFile);
  await mkdir(dirname(destination), { recursive: true });
  await writeFile(destination, html, 'utf8');
  return relativeFile;
}

async function downloadAsset(pathname) {
  if (pathname.startsWith('/wp-content/themes/odd-note/assets/')) {
    return;
  }

  const response = await fetchResponse(new URL(pathname, sourceUrl));
  const relativePath = decodeURIComponent(pathname).replace(/^\/+/, '');
  if (relativePath.split('/').some((segment) => segment === '..')) {
    throw new Error(`안전하지 않은 자산 경로입니다: ${pathname}`);
  }
  const destination = join(temporaryDirectory, relativePath);
  await mkdir(dirname(destination), { recursive: true });
  await writeFile(destination, Buffer.from(await response.arrayBuffer()));
}

try {
  const collections = await Promise.all([
    fetchCollection('posts'),
    fetchCollection('pages'),
    fetchCollection('categories'),
    fetchCollection('tags'),
  ]);

  const queue = [sourceUrl.href];
  for (const record of collections.flat()) {
    if (record.link) {
      queue.push(new URL(record.link, sourceUrl).href);
    }
  }

  const visited = new Set();
  const assets = new Set();
  const routes = [];

  while (queue.length > 0) {
    const nextUrl = new URL(queue.shift());
    nextUrl.hash = '';
    const key = nextUrl.href;

    if (visited.has(key) || !shouldCrawl(nextUrl)) {
      continue;
    }

    if (visited.size >= maximumPages) {
      throw new Error(`페이지가 ${maximumPages}개를 넘어 export를 중단했습니다.`);
    }

    const response = await fetchResponse(nextUrl);
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('text/html')) {
      continue;
    }

    const originalHtml = await response.text();
    visited.add(key);

    for (const pageLink of extractPageLinks(originalHtml)) {
      if (!visited.has(pageLink)) {
        queue.push(pageLink);
      }
    }

    for (const assetPath of extractAssetPaths(originalHtml)) {
      assets.add(assetPath);
    }

    const pathname = nextUrl.pathname.endsWith('/') ? nextUrl.pathname : `${nextUrl.pathname}/`;
    const html = sanitizeHtml(originalHtml, pathname);
    const file = await writeRoute(pathname, html);
    routes.push({ path: pathname, file, indexable: isIndexable(pathname) });
  }

  const missingUrl = new URL('/odd-note-static-export-404/', sourceUrl);
  const missingResponse = await fetchResponse(missingUrl, [404]);
  const missingHtml = sanitizeHtml(await missingResponse.text(), '/404/', true);
  await writeFile(join(temporaryDirectory, '404.html'), missingHtml, 'utf8');

  const feedResponse = await fetchResponse(new URL('/feed/', sourceUrl));
  const feed = (await feedResponse.text())
    .replaceAll(sourceOrigin, canonicalPlaceholder)
    .replace(/[ \t]+$/gm, '');
  await writeFile(join(temporaryDirectory, 'feed.xml'), feed, 'utf8');

  await cp(
    join(projectRoot, 'themes/odd-note/assets'),
    join(temporaryDirectory, 'wp-content/themes/odd-note/assets'),
    { recursive: true },
  );

  for (const assetPath of [...assets].sort()) {
    await downloadAsset(assetPath);
  }

  routes.sort((left, right) => left.path.localeCompare(right.path, 'en'));
  await writeFile(
    join(temporaryDirectory, 'routes.json'),
    `${JSON.stringify({ version: 1, routes }, null, 2)}\n`,
    'utf8',
  );

  await rm(snapshotDirectory, { recursive: true, force: true });
  await rename(temporaryDirectory, snapshotDirectory);
  completed = true;

  console.log(`정적 스냅샷 생성 완료: HTML ${routes.length}개, 추가 자산 ${assets.size}개`);
} finally {
  if (!completed) {
    await rm(temporaryDirectory, { recursive: true, force: true });
  }
}
