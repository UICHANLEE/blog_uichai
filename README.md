# M1 Pro 개발·사업 정보 WordPress 블로그

M1 Pro 맥북에서 WordPress와 MariaDB를 실행하고, 공유기 포트 개방 없이 Cloudflare Tunnel로 공개하는 구성입니다.

    Internet
       |
    Cloudflare
       |
    cloudflared ── WordPress ── MariaDB
       frontend        |          backend 전용
                       |
                 127.0.0.1:8090
                 로컬 확인 전용

## 포함된 것

- WordPress 7.0.2 + PHP 8.3 Apache 공식 ARM64 이미지
- MariaDB 12.3.2 LTS 공식 ARM64 이미지
- Cloudflare Tunnel과 도메인 없는 임시 Quick Tunnel
- DB가 외부에 노출되지 않는 분리 네트워크
- 파일형 Docker secret, 상태 검사, 자동 재시작
- DB·WordPress 파일·운영 설정을 한 세트로 만드는 백업 및 검증 도구
- IT 최신 뉴스·AI 논문 분석·사업 지식을 전면에 둔 인터랙티브 커스텀 테마 `Odd Note`

## 1. 로컬에서 시작

Docker Desktop을 실행한 뒤 프로젝트 폴더에서 다음을 실행합니다.

    make install

이 명령은 공식 이미지를 준비하고 WordPress를 설치한 뒤 `Odd Note` 테마, 한국어 설정, 페이지·카테고리·초기 글·메뉴를 구성합니다. 재실행해도 이미 완료된 사이트를 중복 생성하지 않습니다. 최초 실행에는 몇 분이 걸릴 수 있습니다.

상태를 확인합니다.

    make status
    make doctor

브라우저에서 다음 주소를 엽니다.

    http://127.0.0.1:8090

관리자 화면은 다음 주소입니다.

    http://127.0.0.1:8090/wp-admin/

자동 생성된 로그인 정보는 아래 파일에 있습니다.

- 관리자 아이디: `secrets/wp_admin_user.txt`
- 관리자 비밀번호: `secrets/wp_admin_password.txt`

첫 로그인 직후 다음을 처리하세요.

- **사용자 → 프로필**에서 실제 이메일과 새 비밀번호로 변경합니다.
- 새 비밀번호는 비밀번호 관리자에 보관합니다.
- `문의`와 `개인정보처리방침`의 임시 연락처 문구를 실제 정보로 고칩니다.
- 초기 글 16편은 현재 구축 경험, 개인 지식 노트에서 익명화한 문제의식, 공식 원문 검증을 바탕으로 만든 시작 콘텐츠입니다. 본인의 말투와 추가 경험을 반영해 편집합니다.
- 검색엔진 노출은 의도적으로 꺼져 있습니다. 도메인·필수 페이지·실제 콘텐츠가 준비된 뒤 해제합니다.

설치 후 준비되는 구성은 다음과 같습니다.

- `홈` 정적 페이지와 `전체 글` 글 페이지
- `소개`, `문의`, `개인정보처리방침`, `광고·제휴 고지`
- 핵심 편집 카테고리 `IT 최신 뉴스`, `AI 논문 분석`, `사업 지식`
- 기존 개발 기록을 보존하는 `AI 도구`, `맥 워크플로`, `홈서버 실전` 아카이브
- 헤더와 푸터 메뉴
- 공개 글 16편과 상단 고정 대표 글
- 글 이름 기반 고유주소, 댓글·핑 비활성화, 서울 시간대

WordPress에서 다른 글을 **상단 고정**하면 첫 화면의 `EDITOR’S PICK`으로 우선 노출됩니다.

## 2. Odd Note 인터랙티브 테마

첫 화면은 “놀라움 30%, 읽기 70%”를 기준으로 만들었습니다. 콘텐츠와 링크는 서버가 HTML로 먼저 출력하고, JavaScript는 다음 효과만 덧붙입니다.

편집 흐름은 `신호 → 근거 → 실행`입니다. IT 뉴스에서 중요한 변화를 포착하고, AI 논문에서 주장과 한계를 검증한 뒤, 사업 지식으로 실제 판단과 행동에 연결합니다. 새 핵심 카테고리는 첫 글이 발행되기 전에도 편집 범위와 원칙을 안내하지만 검색 색인에서는 제외됩니다.

- 네이티브 커서를 그대로 둔 채 따라오는 반응형 링과 링크별 `READ`, `OPEN` 라벨
- 마우스를 따라 움직이는 히어로 조명, 카드의 아주 작은 3D 기울기, 마그네틱 CTA
- 스크롤 리빌, 상단 읽기 진행선, 서울 시계
- `ACID`·`CORAL`·`VIOLET` 세 가지 색상 무드
- 헤더의 `FX` 버튼과 운영체제의 동작 줄이기 설정을 따르는 모션 제어
- 터치 기기와 정밀 포인터가 없는 환경에서는 효과를 자동으로 생략하는 정적 화면

사이트 제목은 WordPress 설정값을 사용합니다. 첫 화면 문구와 섹션 구성은 `themes/odd-note/front-page.php`, 색상과 레이아웃은 `themes/odd-note/assets/css/site.css`, 상호작용은 `themes/odd-note/assets/js/site.js`에서 바꿀 수 있습니다.

테마 폴더와 편집 글 원본은 WordPress 컨테이너에 읽기 전용으로 연결됩니다. 컨테이너 안에서 직접 고치지 말고 이 프로젝트의 `themes/odd-note`와 `content/posts`를 원본으로 관리하세요. `content/posts`의 Gutenberg HTML은 부트스트랩 버전이 올라갈 때 해당 글로 발행되며, 같은 슬러그에 사용자가 직접 만든 글이 있으면 자동 변경을 중단합니다. `make backup`의 운영 설정 백업에도 두 원본이 포함됩니다.

## 3. 도메인 없이 외부 연결 시험

다음 명령은 임시 `trycloudflare.com` 주소를 만듭니다.

    make quick-tunnel
    docker compose logs quick-tunnel

로그에 표시되는 URL로 접속합니다. Quick Tunnel은 무작위 임시 주소이며 SLA가 없으므로 네트워크 시험 전용입니다. 미설치 상태에서 실행하면 WordPress 설치 화면까지 인터넷에 공개되므로, 로컬 설치를 먼저 끝낸 뒤 잠깐만 사용하세요. 로컬 주소로 설치한 사이트는 정규 URL 리디렉션 때문에 임시 주소에서 완전히 동작하지 않을 수도 있습니다.

시험을 끝낼 때는 다음처럼 해당 컨테이너만 내립니다.

    docker compose --profile quick-tunnel stop quick-tunnel

## 4. 개인 도메인으로 공개

수익형 사이트는 개인 도메인을 권장합니다. 도메인을 Cloudflare에 연결한 뒤 다음 순서로 설정합니다.

1. Cloudflare Zero Trust에서 remotely-managed Tunnel을 만듭니다.
2. Published application의 hostname을 예를 들어 `blog.example.com`으로 지정합니다.
3. Service URL은 반드시 `http://wordpress:80`으로 지정합니다. 컨테이너 안의 `localhost`는 사용하지 않습니다.
4. 설치 명령에 표시되는 긴 토큰 중 토큰 값만 `secrets/cloudflare_tunnel_token.txt`에 넣습니다.
5. `.env`의 공개 URL을 실제 HTTPS 주소로 바꿉니다.

       WORDPRESS_PUBLIC_URL=https://blog.example.com

6. 운영 Tunnel을 시작합니다.

       make tunnel

7. 상태와 로그를 확인합니다.

       make status
       docker compose logs --tail=100 cloudflared

`WORDPRESS_PUBLIC_URL`을 설정하면 WordPress의 home/site URL과 관리자 HTTPS가 해당 주소로 고정됩니다. Cloudflare의 `X-Forwarded-Proto`도 인식하므로 일반적인 HTTPS 리디렉션 루프를 피합니다.

공유기에서 80/443 포트를 포워딩할 필요가 없습니다. Tunnel은 맥북에서 Cloudflare로 나가는 연결만 만듭니다.

## 5. 백업과 업데이트

현재 상태를 백업합니다.

    make backup
    make verify-backup

백업은 `backups/날짜-시간/`에 다음 파일을 만듭니다.

- `database.sql.gz`: 일관된 MariaDB 논리 덤프
- `wordpress-files.tar.gz`: WordPress 코어, 테마, 플러그인, 업로드
- `configuration.tar.gz`: Compose, 운영 스크립트, Odd Note 테마와 편집 글 원본
- `SHA256SUMS`: 파일 손상 검사용 체크섬

`secrets/`는 백업에 의도적으로 포함하지 않습니다. 해당 폴더와 Cloudflare 복구 정보는 암호화해 별도 위치에 보관하세요. 최근 백업 3~5개 이상을 맥북 밖에도 보관하고, 정기적으로 실제 복구를 시험해야 합니다.

백업 후 공식 이미지를 갱신합니다.

    make update

이미지 버전은 재현성을 위해 `compose.yaml`에 정확히 고정되어 있습니다. WordPress, MariaDB, cloudflared의 보안 패치가 나오면 태그를 새 버전으로 올린 뒤 `make update`를 실행합니다.

볼륨을 삭제하는 `docker compose down -v`는 사이트와 DB 데이터를 지우므로 사용하지 마세요. 일반 종료에는 `make stop` 또는 `make down`을 사용합니다.

## 6. GitHub와 Vercel 정적 배포

Vercel은 이 PHP·MariaDB WordPress 서버를 그대로 실행하지 않습니다. 대신 로컬 WordPress를 비공개 편집기로 유지하고, 공개 페이지를 정적 스냅샷으로 내보내 Vercel에 배포합니다. 관리자·데이터베이스·로그인 정보는 정적 결과물에 포함되지 않습니다.

WordPress에서 글을 발행하거나 디자인을 바꾼 뒤 다음을 실행합니다.

    make export
    make static-build

`static-snapshot/`은 Git에 저장되는 배포 원본이고 `public/`은 Vercel 빌드 과정에서 다시 만들어지는 결과물입니다. 빌드는 localhost 링크를 배포 도메인에 맞게 정리하고, RSS·sitemap·robots.txt를 생성하며, 관리자 경로와 동작하지 않는 WordPress 검색 폼이 남으면 실패합니다.

GitHub 저장소를 Vercel의 **New Project**에서 가져오면 다음 설정은 `vercel.json`에서 자동 적용됩니다.

- Framework Preset: Other
- Build Command: `npm run build`
- Output Directory: `public`
- Production Branch: `main`

처음에는 검색 노출이 꺼진 상태로 배포됩니다. 실제 운영 이메일, 개인정보처리방침, 도메인을 확인한 뒤 Vercel Production 환경에 `ODD_NOTE_INDEXING=1`을 설정하면 indexable 페이지에만 검색 노출이 허용됩니다. 문의 페이지나 개인정보처리방침에 초기 운영 문구가 남아 있으면 공개 빌드가 의도적으로 실패합니다.

Vercel 미리보기와 운영 도메인은 시스템 환경 변수로 자동 반영됩니다. 고정 도메인을 강제로 사용해야 할 때만 `ODD_NOTE_SITE_URL=https://example.com`을 Vercel 환경 변수로 지정하세요.

## 7. 24시간 운영 체크리스트

- Docker Desktop 설정에서 로그인 시 자동 시작을 켭니다.
- 전원 어댑터 연결 시 디스플레이가 꺼져도 자동 잠자기하지 않도록 macOS 배터리 옵션을 설정합니다.
- 2026-08-01 점검 당시 이 맥의 AC 시스템 잠자기는 `sleep 1`로 설정되어 있습니다. 운영 전 **시스템 설정 → 배터리 → 옵션 → 디스플레이가 꺼져 있을 때 전원 어댑터 사용 시 자동 잠자기 방지**를 켜야 합니다.
- 맥북 덮개를 닫으면 잠들 수 있으므로 운용 방식을 실제로 시험합니다.
- 가능하면 유선 Ethernet을 사용하고, 공유기와 광모뎀에도 UPS를 고려합니다.
- FileVault가 켜진 재부팅 뒤에는 사용자가 로그인하기 전 Docker Desktop이 시작되지 않습니다. 이 구성의 가장 큰 무인 복구 제약입니다.
- WordPress 관리자에 2단계 인증을 적용하고, 플러그인과 테마는 검증된 최소 수만 설치합니다.
- 공개 URL을 외부 모니터로 5분 간격 확인합니다.
- 월 1회 이상 백업 성공 여부, 디스크 여유, 컨테이너 재시작 횟수를 확인합니다.

매출에 영향을 줄 정도로 장애 허용 시간이 짧아지면 같은 Compose 구성을 Linux VPS나 전용 미니 PC로 이전하는 것이 좋습니다. M1 Pro의 연산 성능보다 집 회선·전원·로그인 후 복구가 먼저 한계가 됩니다.

## 자주 쓰는 명령

| 목적 | 명령 |
|---|---|
| 공식 이미지 준비 및 시작 | `make up` |
| WordPress 설치와 초기 블로그 구성 | `make install` |
| 상태 | `make status` |
| 종합 진단 | `make doctor` |
| 전체 로그 | `make logs` |
| 운영 Tunnel 시작 | `make tunnel` |
| 백업 | `make backup` |
| 백업 검증 | `make verify-backup` |
| Vercel용 정적 스냅샷 생성 | `make export` |
| 정적 빌드와 검증 | `make static-build` |
| 정적 결과물 다시 검증 | `make static-verify` |
| 정적 결과물 로컬 확인 | `make static-preview` |
| 백업 후 이미지 재생성 | `make update` |
| 고정 버전 이미지 재확인 | `make refresh-images` |
| 안전한 종료 | `make down` |

## 공식 참고 문서

- [WordPress 권장 사양](https://wordpress.org/about/requirements/)
- [WordPress 보안 강화](https://developer.wordpress.org/advanced-administration/security/hardening/)
- [WordPress 백업](https://developer.wordpress.org/advanced-administration/security/backup/)
- [Cloudflare Tunnel 시작](https://developers.cloudflare.com/tunnel/setup/)
- [Cloudflare Tunnel token file](https://developers.cloudflare.com/tunnel/advanced/run-parameters/)
- [Docker Compose secrets](https://docs.docker.com/compose/how-tos/use-secrets/)
- [Docker 컨테이너 자동 재시작](https://docs.docker.com/engine/containers/start-containers-automatically/)
- [Apple의 Mac 잠자기 설정](https://support.apple.com/guide/mac-help/mchle41a6ccd/mac)
