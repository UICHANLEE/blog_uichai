#!/usr/bin/env node

import { access, readFile, readdir } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(scriptDirectory, '..');
const outputDirectory = join(projectRoot, 'public');
const snapshotDirectory = join(projectRoot, 'static-snapshot');

function deploymentOrigin() {
  if (process.env.ODD_NOTE_SITE_URL) {
    return process.env.ODD_NOTE_SITE_URL.replace(/\/$/, '');
  }
  const vercelHost = process.env.VERCEL_PROJECT_PRODUCTION_URL || process.env.VERCEL_URL;
  return vercelHost ? `https://${vercelHost.replace(/^https?:\/\//, '').replace(/\/$/, '')}` : 'http://127.0.0.1:8899';
}

function validateDeploymentOrigin(url, indexingEnabled) {
  const localHostnames = new Set(['127.0.0.1', '0.0.0.0', '::1', 'localhost']);
  const hostname = url.hostname.toLowerCase();
  const hasReservedHostname =
    localHostnames.has(hostname) ||
    hostname.endsWith('.localhost') ||
    hostname.endsWith('.invalid') ||
    hostname.endsWith('.test');

  if (url.username || url.password || url.pathname !== '/' || url.search || url.hash) {
    throw new Error('배포 주소에는 계정 정보, 하위 경로, 쿼리 또는 해시를 사용할 수 없습니다.');
  }
  if ((process.env.VERCEL || indexingEnabled || process.env.ODD_NOTE_SITE_URL) && hasReservedHostname) {
    throw new Error('배포 주소에는 localhost 또는 예약 도메인을 사용할 수 없습니다.');
  }
  if ((process.env.VERCEL || indexingEnabled) && url.protocol !== 'https:') {
    throw new Error('Vercel 및 검색 공개 주소는 https여야 합니다.');
  }
}

async function listFiles(directory) {
  const files = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...await listFiles(path));
    } else {
      files.push(path);
    }
  }
  return files;
}

const indexingEnabled = process.env.ODD_NOTE_INDEXING === '1' && process.env.VERCEL_ENV === 'production';
const originUrl = new URL(deploymentOrigin());
validateDeploymentOrigin(originUrl, indexingEnabled);
const origin = originUrl.origin;
const manifest = JSON.parse(await readFile(join(snapshotDirectory, 'routes.json'), 'utf8'));
const routeFiles = new Map(
  manifest.routes.map((route) => [decodeURIComponent(new URL(route.path, origin).pathname), route.file]),
);

for (const requiredRoute of [
  '/category/ai-tools/',
  '/category/mac-workflow/',
  '/category/home-server/',
  '/category/it-news/',
  '/category/ai-paper-analysis/',
  '/category/business-knowledge/',
  '/supabase-realtime-binary-state-sync/',
  '/spatialvlm-paper-review/',
  '/ai-mvp-before-model/',
  '/ai-cv-sota-briefing-2026-08-23/',
  '/ai-cv-sota-briefing-2026-08-25/',
  '/armorocr-adversarial-ocr-paper-analysis/',
  '/step-pose-video-anomaly-detection-paper-analysis/',
  '/dreamhand-video-diffusion-3d-hand-paper-analysis/',
]) {
  if (!routeFiles.has(requiredRoute)) {
    throw new Error(`필수 카테고리 경로가 없습니다: ${requiredRoute}`);
  }
}

for (const requiredPath of [
  'index.html',
  '404.html',
  'feed.xml',
  'sitemap.xml',
  'robots.txt',
  'wp-content/themes/odd-note/assets/css/site.css',
  'wp-content/themes/odd-note/assets/fonts/pretendard/pretendardvariable-dynamic-subset.css',
  'wp-content/themes/odd-note/assets/fonts/pretendard/LICENSE',
  'wp-content/themes/odd-note/assets/fonts/pretendard/woff2/PretendardVariable.subset.0.woff2',
  'wp-content/themes/odd-note/assets/fonts/pretendard/woff2/PretendardVariable.subset.91.woff2',
  'wp-content/themes/odd-note/assets/js/site.js',
  'wp-content/themes/odd-note/assets/images/og-tech-business.png',
]) {
  await access(join(outputDirectory, requiredPath));
}

const fontCss = await readFile(
  join(outputDirectory, 'wp-content/themes/odd-note/assets/fonts/pretendard/pretendardvariable-dynamic-subset.css'),
  'utf8',
);
if (!fontCss.includes("font-family:'Pretendard Variable'") || !fontCss.includes('./woff2/')) {
  throw new Error('자체 호스팅 Pretendard Variable 선언이 올바르지 않습니다.');
}
if (fontCss.includes('cdn.jsdelivr.net') || fontCss.includes('../../../packages/')) {
  throw new Error('Pretendard CSS에 외부 또는 배포 불가능한 폰트 경로가 남아 있습니다.');
}

const home = await readFile(join(outputDirectory, 'index.html'), 'utf8');
for (const editorialFocus of ['IT 최신 뉴스', 'AI 논문 분석', '사업 지식']) {
  if (!home.includes(editorialFocus)) {
    throw new Error(`첫 화면에서 핵심 편집 주제를 확인할 수 없습니다: ${editorialFocus}`);
  }
}
if (!home.includes('AI CV SOTA 브리핑')) {
  throw new Error('첫 화면에서 최신 AI CV 브리핑을 확인할 수 없습니다.');
}
for (const deepDiveTitle of ['ArmorOCR 논문 분석', 'STEP 논문 분석', 'DreamHand 논문 분석']) {
  if (!home.includes(deepDiveTitle)) {
    throw new Error(`첫 화면에서 독립 심층 분석 글을 확인할 수 없습니다: ${deepDiveTitle}`);
  }
}
if (!home.includes('<link rel="icon" href="data:image/svg+xml,')) {
  throw new Error('기본 사이트 아이콘 fallback이 없습니다.');
}

const editorialArticles = new Map([
  ['/supabase-realtime-binary-state-sync/', ['Supabase Realtime이 바이너리를 품었다', 'supabase.com/changelog/']],
  ['/spatialvlm-paper-review/', ['SpatialVLM은 어디까지 믿을 수 있나', 'arxiv.org/abs/2401.12168']],
  ['/ai-mvp-before-model/', ['AI MVP, 모델부터 만들면 늦는다', 'design.google/library/simulating-intelligence']],
  ['/ai-cv-sota-briefing-2026-08-23/', [
    'AI CV SOTA 브리핑',
    'arxiv.org/abs/2608.20122',
    'arxiv.org/abs/2608.19987',
    'arxiv.org/abs/2608.20308',
  ]],
  ['/ai-cv-sota-briefing-2026-08-25/', [
    'AI CV SOTA 브리핑',
    '<strong>Input</strong>',
    '<strong>Output</strong>',
    'arxiv.org/abs/2608.20492',
    'arxiv.org/abs/2608.21099',
    'arxiv.org/abs/2608.21136',
  ]],
  ['/armorocr-adversarial-ocr-paper-analysis/', [
    'ArmorOCR 논문 분석',
    '<strong>Input</strong>',
    '<strong>Output</strong>',
    'arxiv.org/abs/2608.20122',
    'AdvSpot',
  ]],
  ['/step-pose-video-anomaly-detection-paper-analysis/', [
    'STEP 논문 분석',
    '<strong>Input</strong>',
    '<strong>Output</strong>',
    'arxiv.org/abs/2608.19987',
    'UBnormal',
  ]],
  ['/dreamhand-video-diffusion-3d-hand-paper-analysis/', [
    'DreamHand 논문 분석',
    '<strong>Input</strong>',
    '<strong>Output</strong>',
    'arxiv.org/abs/2608.20308',
    'HOT3D',
  ]],
]);
for (const [route, expectedFragments] of editorialArticles) {
  const content = await readFile(join(outputDirectory, routeFiles.get(route)), 'utf8');
  for (const expected of expectedFragments) {
    if (!content.includes(expected)) {
      throw new Error(`${route}에 필수 편집 정보가 없습니다: ${expected}`);
    }
  }
}

for (const route of manifest.routes) {
  await access(join(outputDirectory, route.file));
}

const forbiddenFragments = [
  '127.0.0.1:8090',
  'localhost:8090',
  'owner@localhost.invalid',
  '/author/owner_',
  '/wp-admin',
  '/wp-json/',
  'xmlrpc.php',
  'wp-emoji-settings',
  'type="speculationrules"',
];

const htmlFiles = (await listFiles(outputDirectory)).filter((file) => file.endsWith('.html'));
const releaseBlockers = [
  '연락 채널을 준비 중입니다',
  '공개 전 확인이 필요한 초기 운영 초안',
];
for (const file of htmlFiles) {
  const content = await readFile(file, 'utf8');
  for (const forbidden of forbiddenFragments) {
    if (content.includes(forbidden)) {
      throw new Error(`${file}에 금지된 문자열이 남아 있습니다: ${forbidden}`);
    }
  }
  if (!content.includes('<html lang="ko-KR"')) {
    throw new Error(`${file}의 문서 언어가 ko-KR이 아닙니다.`);
  }
  if (content.includes('role="search"')) {
    throw new Error(`${file}에 동작하지 않는 WordPress 검색 폼이 남아 있습니다.`);
  }
  if (indexingEnabled) {
    const blocker = releaseBlockers.find((phrase) => content.includes(phrase));
    if (blocker) {
      throw new Error(`${file}에 검색 공개 전 완성해야 할 운영 정보가 있습니다: ${blocker}`);
    }
  }

  for (const match of content.matchAll(/(?:href|src)=["']([^"']+)["']/gi)) {
    const value = match[1];
    if (
      !value ||
      value.startsWith('#') ||
      value.startsWith('data:') ||
      value.startsWith('mailto:') ||
      value.startsWith('tel:') ||
      value.startsWith('javascript:')
    ) {
      continue;
    }

    let candidate;
    try {
      candidate = new URL(value, origin);
    } catch {
      throw new Error(`${file}에 해석할 수 없는 링크가 있습니다: ${value}`);
    }

    if (candidate.origin !== origin) {
      continue;
    }

    const pathname = candidate.pathname;
    if (pathname.startsWith('/category/') && pathname.endsWith('/feed/')) {
      throw new Error(`${file}에 제공하지 않는 카테고리 RSS 링크가 있습니다: ${pathname}`);
    }
    if (pathname.startsWith('/tag/') && pathname.endsWith('/feed/')) {
      throw new Error(`${file}에 제공하지 않는 태그 RSS 링크가 있습니다: ${pathname}`);
    }

    let decodedPathname;
    try {
      decodedPathname = decodeURIComponent(pathname);
    } catch {
      throw new Error(`${file}에 잘못 인코딩된 내부 링크가 있습니다: ${pathname}`);
    }

    const routeFile = routeFiles.get(decodedPathname);
    const target = routeFile
      ? join(outputDirectory, routeFile)
      : pathname.endsWith('/')
        ? join(outputDirectory, decodedPathname.replace(/^\/+/, ''), 'index.html')
        : join(outputDirectory, decodedPathname.replace(/^\/+/, ''));
    await access(target).catch(() => {
      throw new Error(`${file}의 내부 링크 대상이 없습니다: ${pathname}`);
    });
  }
}

for (const route of manifest.routes) {
  const content = await readFile(join(outputDirectory, route.file), 'utf8');
  const expectedUrl = `${origin}${route.path}`;
  if (!content.includes(`<link rel="canonical" href="${expectedUrl}">`)) {
    throw new Error(`${route.path} canonical URL이 올바르지 않습니다.`);
  }
  if (!content.includes(`<meta property="og:url" content="${expectedUrl}">`)) {
    throw new Error(`${route.path} Open Graph URL이 올바르지 않습니다.`);
  }
  if (indexingEnabled && route.indexable && content.includes('noindex')) {
    throw new Error(`${route.path}가 production에서 noindex입니다.`);
  }
  if ((!indexingEnabled || !route.indexable) && !content.includes('noindex')) {
    throw new Error(`${route.path}에 필요한 noindex가 없습니다.`);
  }
}

const feed = await readFile(join(outputDirectory, 'feed.xml'), 'utf8');
const sitemap = await readFile(join(outputDirectory, 'sitemap.xml'), 'utf8');
const robots = await readFile(join(outputDirectory, 'robots.txt'), 'utf8');
for (const [name, content] of [['feed.xml', feed], ['sitemap.xml', sitemap], ['robots.txt', robots]]) {
  if (!content.includes(origin) || content.includes('127.0.0.1:8090')) {
    throw new Error(`${name}의 공개 주소가 올바르지 않습니다.`);
  }
}

console.log(`정적 사이트 검증 통과: HTML ${htmlFiles.length}개, 공개 경로 ${manifest.routes.length}개`);
