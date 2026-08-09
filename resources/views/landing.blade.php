<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="MATOWA は、弓道部・道場向けの弓道記録アプリです。的中記録、立順管理、出欠管理、成績分析をタブレットでまとめて管理できます。">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="MATOWA">
  <meta property="og:title" content="MATOWA | 弓道 記録 アプリ">
  <meta property="og:description" content="弓道部・道場の的中記録、立順管理、出欠管理、成績分析をまとめる団体向け弓道記録アプリ。">
  <meta property="og:image" content="{{ asset('landing/assets/タブレット写真.png') }}">
  <meta name="twitter:card" content="summary_large_image">
  <title>MATOWA | 弓道 記録 アプリ - 的中記録・立順管理・成績分析</title>
  @include('layouts.partials.app-icons')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Noto+Sans+JP:wght@500;700;800;900&display=swap" rel="stylesheet">
  <script>document.documentElement.classList.add("js");</script>
  <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "MATOWA",
      "applicationCategory": "SportsApplication",
      "operatingSystem": "Web",
      "description": "MATOWAは、弓道部・道場向けの弓道記録アプリです。的中記録、立順管理、出欠管理、成績分析をタブレットでまとめて管理できます。",
      "featureList": [
        "弓道の的中記録",
        "弓道部の立順管理",
        "出欠・遅刻管理",
        "グループ成績分析",
        "個人成績のカレンダー表示"
      ]
    }
  </script>
  <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "MATOWAはどんな弓道記録アプリですか？",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "MATOWAは、弓道部や道場など団体で使うことを想定した弓道記録アプリです。的中記録、立順管理、出欠管理、成績分析をひとつのシステムで管理できます。"
          }
        },
        {
          "@type": "Question",
          "name": "弓道部の立順管理にも使えますか？",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "はい。出欠や遅刻を確認しながら立順を作成でき、前回の立をコピーして立順決めの時間を短縮できます。"
          }
        },
        {
          "@type": "Question",
          "name": "スマホやタブレットで使えますか？",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Webアプリとして、インターネット環境があればスマホやタブレットで利用できます。団体利用ではタブレット端末を団体様でご準備ください。"
          }
        }
      ]
    }
  </script>
  <link rel="stylesheet" href="{{ asset('landing/style.css') }}?v={{ filemtime(public_path('landing/style.css')) }}">

</head>
<body>
  <header class="site-header" data-header>
    <a class="logo" href="#top" aria-label="MATOWA ホーム">
      <img class="logo-mark" src="{{ asset('icons/app-icon.png') }}" alt="" aria-hidden="true">
      <span class="logo-text">MATOWA</span>
    </a>

    <nav class="site-nav" aria-label="主要ナビゲーション">
      <a href="#features">機能</a>
      <a href="#intro">導入について</a>
      <a href="#faq">FAQ</a>
      <a href="#contact">お問い合わせ</a>
    </nav>

    <a class="header-login-button" href="{{ route('login') }}">ログイン</a>
  </header>

  <main id="top">
    <section class="hero">
      <div class="hero-bg-ring hero-bg-ring-left" aria-hidden="true"></div>
      <div class="hero-bg-ring hero-bg-ring-right" aria-hidden="true"></div>
      <div class="hero-shape" aria-hidden="true"></div>

      <div class="hero-copy reveal">
        <h1 class="hero-title">
          <span>弓道部・道場の</span>
          <span>記録管理をもっと</span>
          <span>スムーズに。</span>
        </h1>
        <p class="hero-lead">弓道の的中記録・立順管理・成績の振り返りまで、団体運営に必要な記録をひとつのシステムで。</p>
        <p class="hero-sub">タブレット1台で、団体の記録をかんたんに見やすく。</p>

        <div class="hero-actions">
          <a class="button button-primary" href="#features">機能を見る</a>
        </div>
      </div>

      <div class="device-stage reveal">
        <img class="tablet-photo" src="{{ asset('landing/assets/タブレット写真.png') }}" alt="MATOWAの的中記録画面と立順設定画面を表示した2台のタブレット">
      </div>
    </section>

    <section class="seo-section" aria-labelledby="seo-title">
      <div class="seo-content reveal">
        <p class="section-kicker">Kyudo record app</p>
        <h2 id="seo-title">弓道 記録 アプリを、団体運営に合わせて使いやすく。</h2>
        <p>MATOWAは、弓道部や道場の記録管理に特化した弓道記録アプリです。稽古や試合の的中記録、立順管理、出欠・遅刻管理、グループ成績、個人成績をまとめて扱えるため、紙の記録表や表計算で分かれていた作業を整理できます。</p>
        <p>「弓道の的中を記録したい」「部活の立順を早く決めたい」「メンバーごとの成績を振り返りたい」といった団体での記録業務を、スマホやタブレットから確認しやすい形にまとめます。</p>
      </div>
    </section>

    <section class="features" id="features" aria-labelledby="features-title">
      <div class="section-heading reveal">
        <h2 id="features-title">弓道の記録管理を、もっと便利に</h2>
      </div>

      <div class="feature-grid">
        <button class="feature-card feature-button reveal" type="button" data-feature-toggle aria-expanded="false" aria-controls="hit-record-detail">
          <span class="feature-icon record-icon" aria-hidden="true"></span>
          <span class="feature-title">的中記録</span>
          <span class="feature-text">試合や練習の的中結果を簡単に記録。</span>
          <span class="feature-more">詳細を見る</span>
        </button>

        <div class="feature-detail" id="hit-record-detail" hidden>
          <div class="feature-detail-image">
            <img src="{{ asset('landing/assets/記録画面.png') }}" alt="複数人の的中結果を一つの画面で記録できるMATOWAの記録画面">
          </div>
          <div class="feature-detail-copy">
            <p class="section-kicker">Hit record</p>
            <h3>一つのタブレットで、複数人の記録をまとめて入力。</h3>
            <p>団体戦や部活動の稽古でも、射手ごとの的中結果を同じ画面で一度に記録できます。立ごとの流れを見ながら入力できるので、紙の記録表を行き来する手間を減らせます。</p>
          </div>
        </div>

        <button class="feature-card feature-button reveal" type="button" data-feature-toggle aria-expanded="false" aria-controls="order-management-detail">
          <span class="feature-icon people-icon" aria-hidden="true">
            <span></span><span></span><span></span>
          </span>
          <span class="feature-title">立順管理</span>
          <span class="feature-text">出欠と立順をまとめて管理。</span>
          <span class="feature-more">詳細を見る</span>
        </button>

        <div class="feature-detail" id="order-management-detail" hidden>
          <div class="feature-detail-image">
            <img src="{{ asset('landing/assets/立順画面.png') }}" alt="出欠や遅刻を反映しながら立順を作成できるMATOWAの立順管理画面">
          </div>
          <div class="feature-detail-copy">
            <p class="section-kicker">Order management</p>
            <h3>出欠や遅刻を確認しながら、立順をすばやく作成。</h3>
            <p>欠席や遅刻の状況を管理しながら、稽古や試合の立順を作成できます。前回の立をコピーできるので、毎回ゼロから並べ直す手間を減らし、立順決めの時間を短縮できます。</p>
            <p>さらにLINE連携を使うと、部活などのグループLINEに届いた「休みます」「遅刻します」といったメッセージを読み取り、システム上の出欠記録に反映できます。休みや遅刻を一つひとつ確認し直さずに、状況を把握したうえで立順を作成できます。</p>
          </div>
        </div>

        <button class="feature-card feature-button reveal" type="button" data-feature-toggle aria-expanded="false" aria-controls="score-analysis-detail">
          <span class="feature-icon chart-icon" aria-hidden="true">
            <span></span><span></span><span></span>
          </span>
          <span class="feature-title">成績分析</span>
          <span class="feature-text">成績の振り返りと分析で、上達をサポート。</span>
          <span class="feature-more">詳細を見る</span>
        </button>

        <div class="feature-detail" id="score-analysis-detail" hidden>
          <div class="analysis-gallery" data-gallery aria-label="成績分析画面の切り替え">
            <div class="feature-detail-image analysis-image">
              <img data-gallery-panel="group-history" src="{{ asset('landing/assets/グループ履歴.png') }}" alt="グループのランキングを確認できるMATOWAの履歴画面">
              <img data-gallery-panel="group-monthly" src="{{ asset('landing/assets/グループ月間履歴.jpg') }}" alt="グループの月間記録を一覧で印刷できるMATOWAの月間履歴画面" hidden>
              <img data-gallery-panel="personal-history" src="{{ asset('landing/assets/個人履歴.jpg') }}" alt="個人の月ごとの記録と的中率グラフを確認できるMATOWAの個人履歴画面" hidden>
            </div>
            <button class="gallery-arrow gallery-arrow-prev" type="button" data-gallery-prev aria-label="前の画像を見る">&lt;</button>
            <button class="gallery-arrow gallery-arrow-next" type="button" data-gallery-next aria-label="次の画像を見る">&gt;</button>
          </div>
          <div class="feature-detail-copy">
            <p class="section-kicker">Score analysis</p>
            <h3>グループの成績も、個人の成績も見やすく整理。</h3>
            <p>グループのランキングや月間記録をまとめて確認でき、月間記録は印刷して配布や保管にも使えます。</p>
            <p>個人記録では、月ごとの稽古結果をカレンダーで確認できます。その月の的中率グラフを見ながら、調子の変化や振り返るべき稽古日を把握できます。</p>
          </div>
        </div>
      </div>
    </section>

    <section class="intro" id="intro" aria-labelledby="intro-title">
      <div class="intro-copy reveal">
        <p class="section-kicker">Introduction</p>
        <h2 id="intro-title">部活・道場の運用に、自然に入る設計。</h2>
        <p>日々の記録から大会前の確認まで、紙や表計算で分かれていた作業をひとつに集約。スマホでもタブレットでも、必要な情報にすぐ届きます。</p>
        <p class="intro-note">ご利用にあたっては、タブレット端末は団体様でご準備ください。道場や会場でインターネット環境が利用できる状態でお使いいただく必要があります。</p>
      </div>

      <div class="intro-steps reveal" aria-label="導入ステップ">
        <div>
          <span>01</span>
          <strong>メンバーを登録</strong>
          <p>学年や所属、射位の情報をまとめて管理。</p>
        </div>
        <div>
          <span>02</span>
          <strong>稽古や試合を記録</strong>
          <p>的中、立順、をタブレットで入力。</p>
        </div>
        <div>
          <span>03</span>
          <strong>成績を振り返る</strong>
          <p>期間別・メンバー別に推移を確認。</p>
        </div>
      </div>
    </section>

    <section class="faq" id="faq" aria-labelledby="faq-title">
      <div class="section-heading reveal">
        <p class="section-kicker">FAQ</p>
        <h2 id="faq-title">弓道記録アプリについてよくある質問</h2>
      </div>
      <div class="faq-list reveal">
        <article>
          <h3>MATOWAはどんな弓道記録アプリですか？</h3>
          <p>弓道部・道場などの団体向けに、的中記録、立順管理、出欠管理、成績分析をまとめて扱えるWebアプリです。</p>
        </article>
        <article>
          <h3>弓道部の立順管理にも使えますか？</h3>
          <p>はい。出欠や遅刻を確認しながら立順を作成でき、前回の立をコピーすることで立順決めの時間を短縮できます。</p>
        </article>
        <article>
          <h3>スマホやタブレットで使えますか？</h3>
          <p>インターネット環境があれば、スマホやタブレットで利用できます。団体利用の場合、タブレット端末は団体様でご準備ください。</p>
        </article>
      </div>
    </section>

    <section class="contact" id="contact" aria-labelledby="contact-title">
      <div class="contact-panel reveal">
        <div class="contact-copy">
          <p class="section-kicker">Contact</p>
          <h2 id="contact-title">MATOWAについて相談する</h2>
          <p>導入時期や利用人数が決まっていなくても大丈夫です。まずは使い方のイメージからご相談ください。</p>
        </div>

        <form class="contact-form"
              method="POST"
              action="{{ route('contact-inquiries.store') }}"
              aria-label="お問い合わせフォーム">
          @csrf
          <div class="form-field">
            <label for="group-name">団体名</label>
            <input id="group-name" name="groupName" type="text" value="{{ old('groupName') }}" placeholder="例：〇〇高校弓道部" autocomplete="organization" required>
          </div>

          <div class="form-field">
            <label for="representative-name">代表者名</label>
            <input id="representative-name" name="representativeName" type="text" value="{{ old('representativeName') }}" placeholder="例：山田 太郎" autocomplete="name" required>
          </div>

          <label for="email">メールアドレス</label>
          <div class="form-row">
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required>
            <button type="submit">送信する</button>
          </div>
          <p class="form-message" role="status" aria-live="polite">{{ session('contact_success') }}</p>
        </form>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <span>MATOWA</span>
    <span>弓道部・道場の記録管理を、もっとスムーズに。</span>
  </footer>

  <button class="back-to-top" type="button" data-back-to-top aria-label="ページの一番上に戻る">↑</button>

  <script src="{{ asset('landing/script.js') }}?v={{ filemtime(public_path('landing/script.js')) }}"></script>
</body>
</html>
