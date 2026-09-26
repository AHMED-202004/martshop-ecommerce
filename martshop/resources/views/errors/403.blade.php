<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>غير مصرح لك — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/error.css') }}">
</head>
<body class="error-page">
  <main class="card">
    <p class="code" aria-hidden="true">403</p>
    <h1>غير مصرح لك بالدخول</h1>
    <p class="message">حسابك لا يملك الصلاحية المطلوبة لفتح هذه الصفحة.</p>
    <div class="actions">
      @auth
        <a class="primary" href="{{ route('my-account') }}">العودة إلى حسابي</a>
      @else
        <a class="primary" href="{{ route('login') }}">تسجيل الدخول</a>
      @endauth
      <a href="{{ route('home') }}">الصفحة الرئيسية</a>
    </div>
  </main>
</body>
</html>
