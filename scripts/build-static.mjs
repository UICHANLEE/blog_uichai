#!/usr/bin/env node

import { cp, mkdir, readFile, readdir, rename, rm, unlink, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(scriptDirectory, '..');
const snapshotDirectory = join(projectRoot, 'static-snapshot');
const outputDirectory = join(projectRoot, 'public');
const temporaryDirectory = join(projectRoot, '.public-build');
const canonicalPlaceholder = 'https://odd-note.invalid';

function deploymentOrigin() {
  if (process.env.ODD_NOTE_SITE_URL) {
    return process.env.ODD_NOTE_SITE_URL.replace(/\/$/, '');
  }

  const vercelHost = process.env.VERCEL_PROJECT_PRODUCTION_URL || process.env.VERCEL_URL;
  if (vercelHost) {
    return `https://${vercelHost.replace(/^https?:\/\//, '').replace(/\/$/, '')}`;
  }

  return 'http://127.0.0.1:8899';
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

function xmlEscape(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;');
}

async function listFiles(directory) {
  const files = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const absolutePath = join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...await listFiles(absolutePath));
    } else {
      files.push(absolutePath);
    }
  }
  return files;
}

const requestedOrigin = deploymentOrigin();
const originUrl = new URL(requestedOrigin);
if (!['http:', 'https:'].includes(originUrl.protocol)) {
  throw new Error('ODD_NOTE_SITE_URL 형식이 올바르지 않습니다.');
}
const indexingEnabled = process.env.ODD_NOTE_INDEXING === '1' && process.env.VERCEL_ENV === 'production';
validateDeploymentOrigin(originUrl, indexingEnabled);
const origin = originUrl.origin;
const routeManifest = JSON.parse(await readFile(join(snapshotDirectory, 'routes.json'), 'utf8'));

await rm(temporaryDirectory, { recursive: true, force: true });
await mkdir(temporaryDirectory, { recursive: true });
await cp(snapshotDirectory, temporaryDirectory, { recursive: true });

const textExtensions = new Set(['.css', '.html', '.js', '.json', '.txt', '.xml']);
for (const file of await listFiles(temporaryDirectory)) {
  const extension = file.slice(file.lastIndexOf('.'));
  if (!textExtensions.has(extension)) {
    continue;
  }

  let content = await readFile(file, 'utf8');
  content = content.replaceAll(canonicalPlaceholder, origin);
  content = content.replaceAll(
    '<!-- ODD_NOTE_ROBOTS:indexable -->',
    indexingEnabled
      ? '<meta name="robots" content="index, follow, max-image-preview:large">'
      : '<meta name="robots" content="noindex, nofollow">',
  );
  content = content.replaceAll(
    '<!-- ODD_NOTE_ROBOTS:noindex -->',
    '<meta name="robots" content="noindex, follow">',
  );
  await writeFile(file, content, 'utf8');
}

const sitemapRoutes = routeManifest.routes.filter((route) => route.indexable);
const sitemap = [
  '<?xml version="1.0" encoding="UTF-8"?>',
  '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
  ...sitemapRoutes.map((route) => `  <url><loc>${xmlEscape(`${origin}${route.path}`)}</loc></url>`),
  '</urlset>',
  '',
].join('\n');
await writeFile(join(temporaryDirectory, 'sitemap.xml'), sitemap, 'utf8');

const robots = indexingEnabled
  ? `User-agent: *\nAllow: /\nSitemap: ${origin}/sitemap.xml\n`
  : `User-agent: *\nDisallow: /\nSitemap: ${origin}/sitemap.xml\n`;
await writeFile(join(temporaryDirectory, 'robots.txt'), robots, 'utf8');

if (indexingEnabled) {
  const releaseBlockers = [
    '연락 채널을 준비 중입니다',
    '공개 전 확인이 필요한 초기 운영 초안',
  ];
  const htmlFiles = (await listFiles(temporaryDirectory)).filter((file) => file.endsWith('.html'));
  for (const file of htmlFiles) {
    const content = await readFile(file, 'utf8');
    const blocker = releaseBlockers.find((phrase) => content.includes(phrase));
    if (blocker) {
      throw new Error(`검색 공개 전 운영 정보를 완성해야 합니다: ${blocker}`);
    }
  }
}

await unlink(join(temporaryDirectory, 'routes.json'));
await rm(outputDirectory, { recursive: true, force: true });
await rename(temporaryDirectory, outputDirectory);

console.log(`Vercel 정적 빌드 완료: ${routeManifest.routes.length}개 경로, 검색 노출 ${indexingEnabled ? '허용' : '차단'}`);
