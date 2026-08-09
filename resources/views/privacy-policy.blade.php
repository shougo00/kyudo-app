<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="index, follow">
  <meta name="description" content="MATOWAのプライバシーポリシーです。個人情報の取り扱いと保護方針について説明します。">
  <title>プライバシーポリシー | MATOWA</title>
  @include('layouts.partials.app-icons')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Noto+Sans+JP:wght@500;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('landing/style.css') }}?v={{ filemtime(public_path('landing/style.css')) }}">
</head>
<body>
  <header class="site-header">
    <a class="logo" href="/" aria-label="MATOWA ホーム">
      <img class="logo-mark" src="{{ asset('icons/app-icon.png') }}" alt="" aria-hidden="true">
      <span class="logo-text">MATOWA</span>
    </a>

    <nav class="site-nav" aria-label="主要ナビゲーション">
      <a href="/#features">機能</a>
      <a href="/#intro">導入について</a>
      <a href="/#faq">FAQ</a>
      <a href="/#contact">お問い合わせ</a>
    </nav>

    <a class="header-login-button" href="https://kyudo-app.com/login">ログイン</a>
  </header>

  <main class="policy-page">
    <section class="policy-hero" aria-labelledby="policy-title">
      <p class="section-kicker">Privacy Policy</p>
      <h1 id="policy-title">プライバシーポリシー</h1>
      <p>MATOWAは、利用者の個人情報を大切に扱い、適切に保護することを基本方針とします。</p>
    </section>

    <section class="policy-content" aria-label="プライバシーポリシー本文">
      <article>
        <h2>1. 取得する情報</h2>
        <p>当サービスでは、アカウント登録やお問い合わせの際に、氏名、ユーザー名、メールアドレス、団体名など、サービスの提供に必要な範囲の情報を取得することがあります。お問い合わせの際に取得した氏名、メールアドレス、団体名などの情報は、お問い合わせへの回答および必要な連絡のためにのみ使用し、その他の目的には使用しません。</p>
      </article>

      <article>
        <h2>2. 利用目的</h2>
        <p>取得した情報は、ログイン管理、利用者の識別、サービス提供、問い合わせ対応、必要な連絡、機能改善のために利用します。目的の範囲を超えて利用しないよう努めます。</p>
      </article>

      <article>
        <h2>3. 個人情報の保護</h2>
        <p>個人情報は、不正アクセス、紛失、改ざん、漏えいなどが起きないよう、必要な安全管理を行います。利用者の情報をむやみに外部へ公開することはありません。</p>
      </article>

      <article>
        <h2>4. 第三者への提供</h2>
        <p>法令に基づく場合や、利用者本人の同意がある場合を除き、取得した個人情報を第三者へ提供しません。</p>
      </article>

      <article>
        <h2>5. 情報の確認・修正</h2>
        <p>利用者本人から個人情報の確認、修正、削除などの希望があった場合は、本人確認のうえ、合理的な範囲で対応します。</p>
      </article>

      <article>
        <h2>6. お問い合わせ</h2>
        <p>個人情報の取り扱いに関するお問い合わせは、ホームページのお問い合わせフォームよりご連絡ください。</p>
      </article>
    </section>
  </main>

  <footer class="site-footer">
    <span>MATOWA</span>
    <span>弓道部・道場の記録管理を、もっとスムーズに。</span>
    <a href="/privacy-policy">プライバシーポリシー</a>
  </footer>
</body>
</html>
